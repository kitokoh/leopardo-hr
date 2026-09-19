<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailOnlinePaymentService;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailPaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paiement en ligne PUBLIC de la marketplace Leopardo Marché
 * (BC-17 RETAIL, #7812 — chantier BC-21 encaissement).
 *
 * Routes isolées (`throttle:shop-public`, SANS auth — spec §3.2/§6) :
 *   POST /public/market/orders/{reference}/pay → intent de paiement
 *        (jeton `tracking_token` obligatoire, 404 fail-closed — même
 *        contrat que le suivi #7808) ;
 *   POST /public/market/payments/webhook       → réconciliation PSP,
 *        signature HMAC-SHA256 OBLIGATOIRE (`X-Leopardo-Signature`,
 *        secret `services.retail_market.webhook_secret`) — fail-closed
 *        #2615 : secret absent ou signature invalide → 400, JAMAIS
 *        d'écriture.
 *
 * Dépendance assumée : le checkout public est celui de #7808 (branche
 * bc/bc17-marketplace-public) — cette surface n'ajoute AUCUN second
 * pipeline de commande. v1 COD inchangée : le paiement en ligne est un
 * chemin OPT-IN du client final ; non payé, le handoff BC-26 (#7811) porte
 * le montant en COD.
 */
class RetailMarketPaymentPublicController extends Controller
{
    public function __construct(private readonly RetailOnlinePaymentService $payments) {}

    /**
     * POST /public/market/orders/{reference}/pay — crée (ou rejoue) l'intent
     * de paiement de la commande. 404 fail-closed : jeton absent, erroné ou
     * référence inconnue (pas de probing).
     */
    public function pay(Request $request, string $reference): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        /** @var RetailOrder|null $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', $reference)
            ->where('tracking_token', (string) $request->input('token'))
            ->where('source', 'online')
            ->first();

        if (! $order instanceof RetailOrder) {
            abort(404);
        }

        $result = $this->payments->createIntent($order);

        return response()->json(
            ['data' => $this->intentPayload($result['intent'])],
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * POST /public/market/payments/webhook — réconciliation PSP signée.
     * Payload : {provider_reference, status: paid|failed, failure_reason?}.
     * Rejeu idempotent (état final déjà atteint → 200 sans double écriture).
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $this->verifiedPayload($request);

        if ($payload === null) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $providerReference = isset($payload['provider_reference']) && is_string($payload['provider_reference'])
            ? $payload['provider_reference']
            : '';
        $status = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';

        if ($providerReference === '' || ! in_array($status, ['paid', 'failed'], true)) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $failureReason = isset($payload['failure_reason']) && is_string($payload['failure_reason'])
            ? mb_substr($payload['failure_reason'], 0, 255)
            : null;

        $intent = $this->payments->reconcile($providerReference, $status, $failureReason);

        if (! $intent instanceof RetailPaymentIntent) {
            // Référence inconnue : 404 fail-closed (pas de probing des refs).
            abort(404);
        }

        return response()->json(['data' => ['status' => $intent->status->value]]);
    }

    /**
     * Vérification HMAC-SHA256 fail-closed (pattern ChargilyService #2615 /
     * #6561) : secret non configuré = webhook REJETÉ, jamais d'écriture.
     *
     * @return array<string, mixed>|null
     */
    private function verifiedPayload(Request $request): ?array
    {
        $secret = (string) config('services.retail_market.webhook_secret');

        if ($secret === '') {
            Log::error('Retail market webhook: secret not configured - webhook rejected (fail-closed).');

            return null;
        }

        $signatureHeader = (string) $request->header('X-Leopardo-Signature', '');
        $provided = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        if ($provided === '') {
            Log::warning('Retail market webhook: missing or malformed signature header.');

            return null;
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);

        if (! hash_equals($expected, $provided)) {
            Log::warning('Retail market webhook: signature mismatch.');

            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * DTO public strict : aucune donnée interne (ni company_id, ni ids).
     *
     * @return array<string, mixed>
     */
    private function intentPayload(RetailPaymentIntent $intent): array
    {
        return [
            'provider' => $intent->provider,
            'provider_reference' => $intent->provider_reference,
            'status' => $intent->status->value,
            'amount_minor' => (int) $intent->amount_minor,
            'currency' => $intent->currency,
            'checkout_url' => $intent->checkout_url,
        ];
    }
}

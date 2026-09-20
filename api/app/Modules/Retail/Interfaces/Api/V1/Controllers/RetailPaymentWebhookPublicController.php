<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailPaymentService;
use App\Modules\Retail\Infrastructure\Payments\RetailPaymentProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook PUBLIC des providers de paiement marketplace Retail
 * (BC-17 RETAIL, #7812).
 *
 * POST /api/v1/public/market/payments/webhook/{provider}
 *
 * Securite (fail-closed) :
 * - provider inconnu → 404 ;
 * - verification de signature OBLIGATOIRE sur le corps BRUT (HMAC, header
 *   `signature`) AVANT tout parsing metier — invalide ou secret absent →
 *   401, aucun effet ;
 * - payload illisible → 400 ;
 * - evenement inconnu, deja applique (rejeu) ou ignore → 200 SANS double
 *   effet (idempotence portee par RetailPaymentService::applyStatus).
 *
 * Transitions : succeeded → intent `succeeded` + commande payee
 * (payment_status/paid_at + trace RetailOrderPayment) ; failed/expired →
 * statut correspondant, la commande reste non payee.
 */
class RetailPaymentWebhookPublicController extends Controller
{
    public function __construct(
        private readonly RetailPaymentProviderRegistry $providers,
        private readonly RetailPaymentService $payments,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! $this->providers->has($provider)) {
            abort(404);
        }

        $gateway = $this->providers->resolve($provider);

        $rawPayload = (string) $request->getContent();
        $signature = trim((string) $request->header('signature', ''));

        if ($signature === '' || ! $gateway->verifyWebhookSignature($rawPayload, $signature)) {
            // Fail-closed : signature absente, invalide ou secret non
            // configure → 401, AUCUN effet.
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $event = $gateway->parseWebhookEvent($rawPayload);

        if ($event === null) {
            return response()->json(['message' => 'Unreadable webhook payload.'], 400);
        }

        // Rejeu, intent inconnu ou type d'evenement ignore → 200 sans
        // effet : le provider ne doit pas re-livrer indefiniment.
        $applied = $this->payments->handleWebhookEvent($provider, $event);

        return response()->json(['data' => ['applied' => $applied]]);
    }
}

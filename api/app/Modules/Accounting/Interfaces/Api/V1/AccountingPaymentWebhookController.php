<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\OnlinePaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * #5272 — Webhook public des passerelles de paiement (Chargily / Stripe).
 *
 * POST /api/v1/accounting/payment-webhooks/{gateway}
 * Aucune auth Sanctum : la confiance est portée par la signature HMAC
 * (fail-closed) et les métadonnées document_id/company_id envoyées au
 * checkout. Le tenant est résolu par metadata puis posé via TenantManager.
 */
final class AccountingPaymentWebhookController extends Controller
{
    public function __construct(private readonly OnlinePaymentService $service) {}

    public function __invoke(string $gateway, Request $request): JsonResponse
    {
        $signatureHeader = $gateway === 'stripe'
            ? (string) $request->header('Stripe-Signature', '')
            : (string) $request->header('X-Chargily-Signature', '');

        try {
            $this->service->handleWebhook($gateway, $request->getContent(), $signatureHeader);
        } catch (QueryException $e) {
            // #6553 (audit) : deux livraisons CONCURRENTES du même paiement
            // violent l'index unique accounting_payments_company_gateway_unique
            // (23505) — la vérification `existing` puis l'insert ne sont pas
            // atomiques. La contrainte unique est le verrou final : le perdant
            // de la course répond « rejoué » (200 idempotent), jamais 500.
            if ($e->getCode() === '23505') {
                Log::info('Accounting webhook: concurrent delivery deduplicated by unique index (23505)', [
                    'gateway' => $gateway,
                ]);

                return new JsonResponse(['received' => true, 'replayed' => true]);
            }

            throw $e;
        }

        return new JsonResponse(['received' => true]);
    }
}

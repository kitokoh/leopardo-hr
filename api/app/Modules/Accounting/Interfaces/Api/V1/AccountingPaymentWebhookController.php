<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\OnlinePaymentService;
use App\Modules\Accounting\Domain\Exceptions\PaymentAmountMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        } catch (PaymentAmountMismatchException $e) {
            // #6553 — montant notifié > solde restant (anti-fraude US2.4) :
            // réponse 422 explicite pour que la passerelle cesse les retries.
            Log::warning('Accounting webhook: notified amount exceeds balance - 422', [
                'gateway' => $gateway,
            ]);

            return new JsonResponse([
                'error' => 'PAYMENT_AMOUNT_MISMATCH',
                'message' => 'PAYMENT_AMOUNT_MISMATCH',
                'localized_message' => __('errors.PAYMENT_AMOUNT_MISMATCH'),
            ], 422);
        }

        return new JsonResponse(['received' => true]);
    }
}

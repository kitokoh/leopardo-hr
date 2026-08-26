<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\OnlinePaymentService;
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

        $this->service->handleWebhook($gateway, $request->getContent(), $signatureHeader);

        return new JsonResponse(['received' => true]);
    }
}

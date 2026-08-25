<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\OnlinePaymentService;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\CheckoutRequest;
use Illuminate\Http\JsonResponse;

/**
 * #5272 — Paiement en ligne des documents (portail client / comptable).
 *
 * POST /api/v1/accounting/documents/{document}/checkout
 * RBAC : comptable / principal (middleware api.manager sur la route). Le
 * portail client sécurisé (#5357/#5403) consommera ce même endpoint derrière
 * son propre mécanisme d'auth.
 */
final class AccountingCheckoutController extends Controller
{
    public function __construct(private readonly OnlinePaymentService $service) {}

    public function store(string $document, CheckoutRequest $request): JsonResponse
    {
        /** @var AccountingDocument $documentModel */
        $documentModel = AccountingDocument::query()->findOrFail((int) $document);

        $baseUrl = rtrim((string) config('app.url'), '/');
        $successUrl = $request->filled('success_url')
            ? (string) $request->validated('success_url')
            : $baseUrl.'/portal/documents/'.$documentModel->id.'?status=paid';
        $cancelUrl = $request->filled('cancel_url')
            ? (string) $request->validated('cancel_url')
            : $baseUrl.'/portal/documents/'.$documentModel->id.'?status=cancelled';

        $checkout = $this->service->createCheckout($documentModel, $successUrl, $cancelUrl);

        return response()->json(['data' => $checkout]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use App\Modules\Accounting\Infrastructure\Services\PaymentReminderService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\PaymentRegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trésorerie Phase B (issue #5229) — paiements, rapprochement, relances.
 * RBAC : comptable / principal (middleware api.manager sur les routes).
 */
final class AccountingPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentRegistrationService $payments,
        private readonly PaymentReminderService $reminders,
    ) {}

    /**
     * GET /api/v1/accounting/payments?document_id=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $documentId = $request->integer('document_id') > 0 ? $request->integer('document_id') : null;
        $status = $request->string('status')->toString();
        $status = in_array($status, ['pending', 'recorded', 'matched'], true) ? $status : null;

        $payments = $this->payments->list($documentId, $status);

        return response()->json([
            'data' => $payments->map(static fn (AccountingPayment $payment): array => [
                'id' => $payment->id,
                'document_id' => $payment->document_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'received_at' => $payment->received_at?->toDateString(),
                'reconciled_at' => $payment->reconciled_at?->toIso8601String(),
                'status' => $payment->status,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/payments
     */
    public function store(string $document, PaymentRegisterRequest $request): JsonResponse
    {
        /** @var AccountingDocument $documentModel */
        $documentModel = AccountingDocument::query()->findOrFail((int) $document);

        $payment = $this->payments->register(
            document: $documentModel,
            amount: (float) $request->validated('amount'),
            method: (string) $request->validated('method'),
            reference: $request->validated('reference') !== null ? (string) $request->validated('reference') : null,
            receivedAt: $request->filled('received_at') ? \Illuminate\Support\Carbon::parse((string) $request->validated('received_at')) : null,
        );

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'document_id' => $payment->document_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'status' => $payment->status,
                'document_paid_amount' => $documentModel->refresh()->paid_amount,
                'document_status' => $documentModel->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/accounting/payments/{payment}/reconcile
     */
    public function reconcile(string $payment, Request $request): JsonResponse
    {
        /** @var AccountingPayment $paymentModel */
        $paymentModel = AccountingPayment::query()->findOrFail((int) $payment);

        $reconciled = $this->payments->reconcile($paymentModel);

        return response()->json([
            'data' => [
                'id' => $reconciled->id,
                'status' => $reconciled->status,
                'reconciled_at' => $reconciled->reconciled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/accounting/reminders/run
     */
    public function runReminders(Request $request): JsonResponse
    {
        $sent = $this->reminders->run();

        return response()->json(['reminders_sent' => $sent]);
    }
}

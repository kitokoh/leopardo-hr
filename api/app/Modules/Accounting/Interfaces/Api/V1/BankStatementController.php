<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Domain\Models\BankStatementLine;
use App\Modules\Accounting\Infrastructure\Services\BankReconciliationService;
use App\Modules\Accounting\Infrastructure\Services\BankStatementImportService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\BankStatementImportRequest;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\MatchBankStatementLineRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Rapprochement bancaire Phase D (issue #5435) — import de relevé, matching
 * auto/manuel, état de rapprochement. RBAC : comptable / principal
 * (middleware api.manager sur les routes). Isolation tenant fail-closed via
 * le scope global BelongsToCompany (résolution de modèle → 404 cross-tenant).
 */
final class BankStatementController extends Controller
{
    public function __construct(
        private readonly BankStatementImportService $importService,
        private readonly BankReconciliationService $reconciliationService,
    ) {}

    /**
     * POST /api/v1/accounting/bank-statements/import
     */
    public function import(BankStatementImportRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $period = $request->validated('statement_period');
        $reference = $request->validated('import_reference');
        $result = $this->importService->import(
            companyId: currentCompany()->id,
            statementPeriod: is_string($period) ? $period : '',
            importReference: is_string($reference) ? $reference : '',
            csvContent: (string) $file->get(),
        );

        /** @var BankStatement $statement */
        $statement = $result['statement'];

        return response()->json([
            'data' => [
                'statement' => $this->serializeStatement($statement),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/accounting/bank-statements?status=
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['imported', 'reconciling', 'reconciled'], true) ? $status : null;

        $statements = BankStatement::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $statements->map(fn (BankStatement $statement): array => $this->serializeStatement($statement))->values(),
        ]);
    }

    /**
     * GET /api/v1/accounting/bank-statements/{statement}
     */
    public function show(BankStatement $statement): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeStatement($statement),
        ]);
    }

    /**
     * POST /api/v1/accounting/bank-statements/{statement}/reconcile
     */
    public function reconcile(BankStatement $statement): JsonResponse
    {
        $result = $this->reconciliationService->autoReconcile($statement);

        return response()->json([
            'data' => array_merge($result, $this->reconciliationService->status($statement)),
        ]);
    }

    /**
     * POST /api/v1/accounting/bank-statement-lines/{line}/match
     */
    public function match(BankStatementLine $line, MatchBankStatementLineRequest $request): JsonResponse
    {
        $paymentId = $request->validated('payment_id');
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::query()->findOrFail(is_numeric($paymentId) ? (int) $paymentId : 0);

        $this->reconciliationService->matchManually($line, $payment);

        return response()->json([
            'data' => [
                'line_id' => $line->id,
                'status' => $line->status,
                'matched_payment_id' => $line->matched_payment_id,
                'confidence' => $line->confidence,
                'payment_status' => $payment->status,
                'reconciled_at' => $payment->reconciled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/accounting/bank-statements/{statement}/status
     */
    public function status(BankStatement $statement): JsonResponse
    {
        return response()->json([
            'data' => $this->reconciliationService->status($statement),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStatement(BankStatement $statement): array
    {
        return [
            'id' => $statement->id,
            'statement_period' => $statement->statement_period,
            'import_reference' => $statement->import_reference,
            'opening_balance' => $statement->opening_balance,
            'closing_balance' => $statement->closing_balance,
            'status' => $statement->status,
            'created_at' => $statement->created_at?->toIso8601String(),
            'lines' => $statement->lines()
                ->orderBy('line_number')
                ->get()
                ->map(static fn (BankStatementLine $line): array => [
                    'id' => $line->id,
                    'line_number' => $line->line_number,
                    'line_date' => $line->line_date->toDateString(),
                    'label' => $line->label,
                    'amount' => $line->amount,
                    'external_reference' => $line->external_reference,
                    'status' => $line->status,
                    'matched_payment_id' => $line->matched_payment_id,
                    'confidence' => $line->confidence,
                ])->values(),
        ];
    }
}

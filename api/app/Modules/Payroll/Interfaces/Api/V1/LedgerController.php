<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\LedgerEntry;
use App\Modules\Payroll\Infrastructure\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PA2-PAY-007 — Ledger financier employee.
 *
 * Read-only access to the auditable journal of advances, payments, and
 * balance adjustments for an employee.
 */
class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledgerService) {}

    /**
     * GET /me/ledger — the authenticated employee's own history.
     */
    public function myLedger(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return $this->historyResponse($request, $actor);
    }

    /**
     * GET /employees/{employee}/ledger — a manager viewing an employee's
     * history within the same company (or the employee viewing their own).
     */
    public function employeeLedger(Request $request, int $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->id !== $employee && ! $actor->isManager()) {
            abort(403);
        }

        /** @var Employee|null $target */
        $target = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($employee);

        if ($target === null) {
            abort(404);
        }

        return $this->historyResponse($request, $target);
    }

    private function historyResponse(Request $request, Employee $employee): JsonResponse
    {
        $entryType = $request->input('entry_type');
        if ($entryType !== null && ! in_array($entryType, LedgerEntry::TYPES, true)) {
            abort(422, 'Invalid entry_type filter.');
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $entries = $this->ledgerService->history($employee, $perPage, $entryType);

        return response()->json([
            'data' => $entries->getCollection()->map(fn (LedgerEntry $entry): array => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'balance_after' => $entry->balance_after,
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'payment_document_id' => $entry->payment_document_id,
                'created_by' => $entry->created_by,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'current_balance' => $this->ledgerService->currentBalance($employee),
            ],
        ]);
    }
}

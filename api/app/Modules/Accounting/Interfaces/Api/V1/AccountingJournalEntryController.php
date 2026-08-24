<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal des écritures salariales — flux Paie → Comptabilité (issue #5239,
 * Phase C, Partie 1).
 *
 * Lecture seule, RBAC comptable/principal (porté par le middleware
 * api.manager sur les routes) : la génération est déclenchée à la validation
 * du run de paie (événement PayrollRunValidated) et rattrapable par la
 * commande `accounting:generate-payroll-entries`. Isolation tenant par
 * BelongsToCompany (404 fail-closed #3727).
 */
final class AccountingJournalEntryController extends Controller
{
    /**
     * GET /api/v1/accounting/journal-entries?payroll_run_id=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $runId = $request->integer('payroll_run_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, AccountingJournalEntry> $entries */
        $entries = AccountingJournalEntry::query()
            ->when($runId > 0, static fn (Builder $query) => $query->where('payroll_run_id', $runId))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return response()->json([
            'data' => collect($entries->items())->map(
                static fn (AccountingJournalEntry $entry): array => [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date?->toDateString(),
                    'payroll_run_id' => $entry->payroll_run_id,
                    'pay_slip_id' => $entry->pay_slip_id,
                    'employee_id' => $entry->employee_id,
                    'account_code' => $entry->account_code,
                    'account_label' => $entry->account_label,
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'reference' => $entry->reference,
                    'source' => $entry->source,
                ]
            )->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/accounting/journal-entries/{entry}
     */
    public function show(AccountingJournalEntry $entry): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date?->toDateString(),
                'payroll_run_id' => $entry->payroll_run_id,
                'pay_slip_id' => $entry->pay_slip_id,
                'employee_id' => $entry->employee_id,
                'account_code' => $entry->account_code,
                'account_label' => $entry->account_label,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'reference' => $entry->reference,
                'source' => $entry->source,
                'created_at' => $entry->created_at?->toIso8601String(),
            ],
        ]);
    }
}

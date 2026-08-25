<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\AccountingLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grand livre + balance de vérification — issue #5422.
 *
 * RBAC : comptable / principal (middleware `api.manager:principal,comptable`
 * posé sur les routes, même convention que le journal #5234).
 *
 * Isolation tenant : company_id est TOUJOURS dérivé de l'utilisateur
 * authentifié — aucun id d'entreprise dans l'URL — et le service porte un
 * WHERE company_id explicite sur chaque requête (le scope BelongsToCompany
 * ne couvre pas les requêtes manuelles/agrégées — fail-closed #3727).
 */
final class AccountingLedgerController extends Controller
{
    /**
     * GET /api/v1/accounting/ledger?period=YYYY-MM&account_code=&per_page=
     * Grand livre paginé, enrichi du solde cumulé par écriture et du solde
     * d'ouverture (méta) lorsque le compte est filtré.
     */
    public function index(Request $request, AccountingLedgerService $service): JsonResponse
    {
        $validated = $request->validate([
            'account_code' => ['nullable', 'string', 'max:20'],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = (string) $request->user()?->getAttribute('company_id');
        $accountCode = isset($validated['account_code']) ? (string) $validated['account_code'] : null;
        $period = (string) $validated['period'];
        $perPage = (int) ($validated['per_page'] ?? 20);

        $ledger = $service->ledger($companyId, $accountCode, $period, $perPage);

        return response()->json([
            'data' => $ledger->getCollection()
                ->map(static fn (AccountingJournalEntry $entry): array => [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date->toDateString(),
                    'period' => $entry->period,
                    'account_code' => $entry->account_code,
                    'account_label' => $entry->account_label,
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'piece' => $entry->piece,
                    'description' => $entry->description,
                    'running_balance' => (float) $entry->getAttribute('running_balance'),
                ])
                ->values(),
            'meta' => [
                'current_page' => $ledger->currentPage(),
                'last_page' => $ledger->lastPage(),
                'per_page' => $ledger->perPage(),
                'total' => $ledger->total(),
                'opening_balance' => $service->openingBalance($companyId, $accountCode, $period),
                'account_code' => $accountCode,
            ],
        ]);
    }

    /**
     * GET /api/v1/accounting/balance?period=YYYY-MM
     * Balance de vérification : totaux débit/crédit et solde par compte,
     * totaux généraux et indicateur d'équilibre.
     */
    public function balance(Request $request, AccountingLedgerService $service): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $companyId = (string) $request->user()?->getAttribute('company_id');
        $period = (string) $validated['period'];

        $balance = $service->balance($companyId, $period);

        return response()->json([
            'data' => $balance['data'],
            'meta' => [
                'period' => $period,
                'totals' => [
                    'total_debit' => $balance['totals']['total_debit'],
                    'total_credit' => $balance['totals']['total_credit'],
                    'difference' => $balance['totals']['ecart'],
                ],
                'balanced' => $balance['balanced'],
            ],
        ]);
    }
}

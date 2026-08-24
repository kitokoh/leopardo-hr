<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Expense\Domain\Models\ExpenseAccountingEntry;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5235 — Phase C : écritures comptables des notes de frais.
 *
 * À l'approbation d'un `ExpenseClaim`, persiste 2 lignes équilibrées par
 * construction (D 625 « Frais généraux » / C 512 « Banque » = total de la
 * note) avec référence traçable `EXPENSE-CLAIM-{id}`. Régénération
 * IDEMPOTENTE (remplacement des lignes du claim, contrainte unique
 * (claim, compte)). Isolation tenant par `company_id`.
 *
 * Comptes par défaut (PCG minimal) : 625 charges / 512 banque — surcharge
 * ultérieure possible via le plan comptable du module Accounting.
 */
class ExpenseAccountingEntryService
{
    public const ACCOUNT_EXPENSE = '625';

    public const ACCOUNT_BANK = '512';

    public const LABEL_EXPENSE = 'Frais généraux';

    public const LABEL_BANK = 'Banque';

    /**
     * Génère (ou régénère) les écritures comptables d'une note approuvée.
     *
     * @return int nombre de lignes persistées (toujours 2)
     */
    public function generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int
    {
        if ($claim->status !== 'approved') {
            throw new \RuntimeException(
                'Seule une note de frais approuvée génère des écritures comptables (statut actuel : '.$claim->status.').'
            );
        }

        $amount = round((float) $claim->total_amount, 2);
        $reference = sprintf('EXPENSE-CLAIM-%d', $claim->id);

        return DB::transaction(function () use ($claim, $amount, $reference, $actor): int {
            // Idempotence : on remplace les lignes existantes du claim.
            ExpenseAccountingEntry::query()
                ->where('expense_claim_id', $claim->id)
                ->delete();

            $now = now();
            $rows = [
                [
                    'company_id' => $claim->company_id,
                    'expense_claim_id' => $claim->id,
                    'date' => $claim->approved_at?->toDateString() ?? now()->toDateString(),
                    'account_code' => self::ACCOUNT_EXPENSE,
                    'account_label' => self::LABEL_EXPENSE,
                    'debit' => $amount,
                    'credit' => 0,
                    'reference' => $reference,
                    'created_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $claim->company_id,
                    'expense_claim_id' => $claim->id,
                    'date' => $claim->approved_at?->toDateString() ?? now()->toDateString(),
                    'account_code' => self::ACCOUNT_BANK,
                    'account_label' => self::LABEL_BANK,
                    'debit' => 0,
                    'credit' => $amount,
                    'reference' => $reference,
                    'created_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            ExpenseAccountingEntry::query()->insert($rows);

            Log::info('expense.accounting_entries.generated', [
                'expense_claim_id' => $claim->id,
                'company_id' => $claim->company_id,
                'amount' => $amount,
                'by' => $actor?->id,
            ]);

            return count($rows);
        });
    }

    /** @return Collection<int, ExpenseAccountingEntry> */
    public function entriesForClaim(ExpenseClaim $claim): Collection
    {
        return ExpenseAccountingEntry::query()
            ->where('company_id', $claim->company_id)
            ->where('expense_claim_id', $claim->id)
            ->orderBy('account_code')
            ->get();
    }

    /** Écart débit − crédit du claim (0.0 = équilibré). */
    public function balanceForClaim(ExpenseClaim $claim): float
    {
        return (float) ExpenseAccountingEntry::query()
            ->where('expense_claim_id', $claim->id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')
            ->value('balance');
    }

    /**
     * Supprime toutes les écritures d'un claim (rejet après approbation,
     * régénération).
     *
     * @return int nombre de lignes supprimées
     */
    public function deleteForClaim(ExpenseClaim $claim): int
    {
        return ExpenseAccountingEntry::query()
            ->where('expense_claim_id', $claim->id)
            ->delete();
    }
}

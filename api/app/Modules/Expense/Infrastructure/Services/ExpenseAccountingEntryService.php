<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Expense\Domain\Exceptions\UnbalancedExpenseEntriesException;
use App\Modules\Expense\Domain\Models\ExpenseAccountingEntry;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5235 — Phase C : notes de frais → écritures comptables.
 *
 * Persiste les lignes d'écriture d'une `ExpenseClaim` approuvée (partie
 * double : débit charge / crédit personnel). Le module Expense reste maître
 * du workflow des notes de frais ; le module Accounting consomme ces lignes.
 *
 * - Équilibre débit = crédit garanti (exception sinon) ;
 * - régénération IDEMPOTENTE (remplacement des lignes de la note, contrainte
 *   d'unicité (expense_claim_id, account_code)) ;
 * - traçabilité : chaque ligne porte `reference = EXPENSE-{id}` et
 *   l'`expense_claim_id` ; isolation tenant par `company_id`.
 *
 * Hors périmètre (FOCUS) : aucune modification du workflow Expense existant
 * (ExpenseClaimController) ni du moteur Payroll (#5239 gère le volet paie).
 */
class ExpenseAccountingEntryService
{
    /**
     * Génère (ou régénère) les écritures comptables d'une note approuvée.
     *
     * @return int nombre de lignes persistées (2 par défaut, 0 si rien)
     *
     * @throws UnbalancedExpenseEntriesException si débit ≠ crédit
     */
    public function generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int
    {
        if ($claim->status !== 'approved') {
            throw new \RuntimeException(
                'Une note de frais doit être approuvée avant génération des écritures comptables (statut actuel : '.$claim->status.').'
            );
        }

        $lines = $this->journalLines($claim);
        if ($lines === []) {
            return 0;
        }

        $balance = $this->balanceOf($lines);
        if (abs($balance) > 0.004) {
            throw new UnbalancedExpenseEntriesException(
                "Journal déséquilibré pour la note {$claim->id} : débit − crédit = {$balance}"
            );
        }

        return DB::transaction(function () use ($claim, $lines, $actor): int {
            // Idempotence : on remplace les lignes existantes de la note.
            ExpenseAccountingEntry::query()
                ->where('expense_claim_id', $claim->id)
                ->delete();

            $now = now();
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    'company_id' => $claim->company_id,
                    'expense_claim_id' => $claim->id,
                    'employee_id' => $line['employee_id'] ?? null,
                    'date' => $line['date'] ?? ($claim->approved_at?->toDateString() ?? now()->toDateString()),
                    'account_code' => $line['account_code'],
                    'account_label' => $line['account_label'] ?? '',
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'reference' => $line['reference'] ?? sprintf('EXPENSE-%d', $claim->id),
                    'created_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ExpenseAccountingEntry::query()->insert($rows);

            Log::info('expense.accounting_entries.generated', [
                'expense_claim_id' => $claim->id,
                'company_id' => $claim->company_id,
                'lines' => count($rows),
                'by' => $actor?->id,
            ]);

            return count($rows);
        });
    }

    /**
     * Supprime les écritures d'une note (rejet d'une note approuvée).
     *
     * @return int nombre de lignes supprimées
     */
    public function voidForClaim(ExpenseClaim $claim, ?Employee $actor = null): int
    {
        $deleted = DB::transaction(function () use ($claim): int {
            /** @var int $deletedRows */
            $deletedRows = ExpenseAccountingEntry::query()
                ->where('expense_claim_id', $claim->id)
                ->delete();

            return $deletedRows;
        });

        if ($deleted > 0) {
            Log::info('expense.accounting_entries.voided', [
                'expense_claim_id' => $claim->id,
                'company_id' => $claim->company_id,
                'lines' => $deleted,
                'by' => $actor?->id,
            ]);
        }

        return $deleted;
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

    /** Écart débit − crédit de la note (0.0 = équilibré). */
    public function balanceForClaim(ExpenseClaim $claim): float
    {
        $balance = ExpenseAccountingEntry::query()
            ->where('expense_claim_id', $claim->id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')
            ->value('balance');

        return (float) (is_numeric($balance) ? $balance : 0.0);
    }

    /**
     * Lignes d'écriture (partie double) d'une note approuvée.
     *
     * Débit : compte de charge de la catégorie DOMINANTE de la note (celle
     * dont le montant cumulé est le plus élevé, départage stable par ordre
     * alphabétique de catégorie). Crédit : 425 « Personnel — avances et
     * acomptes (remboursement de frais) ». Montant : total de la note.
     *
     * @return array<int, array<string, mixed>>
     */
    private function journalLines(ExpenseClaim $claim): array
    {
        $date = $claim->approved_at?->toDateString() ?? now()->toDateString();
        $reference = sprintf('EXPENSE-%d', $claim->id);
        $total = round((float) $claim->total_amount, 2);

        if ($total <= 0.0) {
            return [];
        }

        $chargeAccount = ExpenseClaimChartOfAccounts::forCategory($this->dominantCategory($claim));
        $creditAccount = ExpenseClaimChartOfAccounts::counterpartAccount();

        return [
            [
                'date' => $date,
                'company_id' => (string) $claim->company_id,
                'expense_claim_id' => $claim->id,
                'employee_id' => $claim->employee_id,
                'account_code' => $chargeAccount['code'],
                'account_label' => $chargeAccount['label'],
                'debit' => $total,
                'credit' => 0.0,
                'reference' => $reference,
            ],
            [
                'date' => $date,
                'company_id' => (string) $claim->company_id,
                'expense_claim_id' => $claim->id,
                'employee_id' => $claim->employee_id,
                'account_code' => $creditAccount['code'],
                'account_label' => $creditAccount['label'],
                'debit' => 0.0,
                'credit' => $total,
                'reference' => $reference,
            ],
        ];
    }

    /**
     * Catégorie dominante de la note : montant cumulé le plus élevé,
     * départage stable par ordre alphabétique (déterminisme de la régénération).
     */
    private function dominantCategory(ExpenseClaim $claim): string
    {
        /** @var array<string, float> $totals */
        $totals = [];
        foreach ($claim->items as $item) {
            $category = (string) ($item->category ?? 'other');
            $totals[$category] = ($totals[$category] ?? 0.0) + (float) $item->amount;
        }

        if ($totals === []) {
            return 'other';
        }

        $best = 'other';
        $bestAmount = -1.0;
        foreach ($totals as $category => $amount) {
            if ($amount > $bestAmount || ($amount === $bestAmount && $category < $best)) {
                $best = $category;
                $bestAmount = $amount;
            }
        }

        return $best;
    }

    /**
     * Écart débit − crédit d'un tableau de lignes.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function balanceOf(array $lines): float
    {
        $debits = array_sum(array_map(
            static function (array $l): float {
                $debit = $l['debit'] ?? 0;

                return is_numeric($debit) ? (float) $debit : 0.0;
            },
            $lines
        ));
        $credits = array_sum(array_map(
            static function (array $l): float {
                $credit = $l['credit'] ?? 0;

                return is_numeric($credit) ? (float) $credit : 0.0;
            },
            $lines
        ));

        return round($debits - $credits, 2);
    }
}

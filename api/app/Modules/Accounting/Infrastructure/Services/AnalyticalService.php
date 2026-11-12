<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Models\AccountingBudget;
use App\Modules\Accounting\Domain\Models\AccountingCostCenter;
use Illuminate\Support\Facades\DB;

/**
 * Comptabilité analytique et suivi budgétaire (issue #9375).
 *
 * Le module n'avait aucun axe analytique exploitable : `project_ref` était une
 * chaîne libre, jamais reportée sur l'écriture, et rien ne comparait un réalisé
 * à un budget.
 *
 * Ce service répond à deux questions :
 *   - **réalisé par axe** : charges et produits effectivement comptabilisés sur
 *     un axe, sur une période ;
 *   - **consommation budgétaire** : réalisé vs enveloppe, avec le reste
 *     disponible et le taux de consommation.
 *
 * Le réalisé est lu sur les **écritures** (pas sur les documents) : c'est le
 * seul endroit où la ventilation analytique est définitive, et cela évite de
 * compter deux fois un document repris par un avoir.
 */
class AnalyticalService
{
    /**
     * Réalisé par axe sur une période.
     *
     * @return list<array{cost_center_id: int|null, code: string|null, label: string|null, charges: float, products: float, net: float}>
     */
    public function actualsByCostCenter(string $companyId, string $from, string $to): array
    {
        $rows = DB::table('accounting_journal_entries as e')
            ->leftJoin('accounting_cost_centers as c', 'c.id', '=', 'e.cost_center_id')
            ->selectRaw('e.cost_center_id as cost_center_id')
            ->selectRaw('MAX(c.code) as code')
            ->selectRaw('MAX(c.label) as label')
            // Charges = classes 6 (PCG/SYSCOHADA) et 7 (Tekdüzen) ; produits =
            // classes 7 (PCG/SYSCOHADA) et 6 (Tekdüzen). On ne devine pas le
            // référentiel : on agrège par sens du solde, ce qui reste juste dans
            // les deux conventions.
            ->selectRaw('COALESCE(SUM(e.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(e.credit), 0) as total_credit')
            ->where('e.company_id', $companyId)
            ->whereBetween('e.entry_date', [$from, $to])
            ->groupBy('e.cost_center_id')
            ->orderBy('e.cost_center_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $result[] = [
                'cost_center_id' => $row->cost_center_id !== null ? (int) $row->cost_center_id : null,
                'code' => $row->code !== null ? (string) $row->code : null,
                'label' => $row->label !== null ? (string) $row->label : null,
                'charges' => round($debit, 2),
                'products' => round($credit, 2),
                'net' => round($debit - $credit, 2),
            ];
        }

        return $result;
    }

    /**
     * Consommation budgétaire d'un exercice.
     *
     * Pour chaque budget : réalisé = somme des débits des écritures de l'axe
     * dont le compte commence par `account_prefix` (toutes natures si null).
     *
     * @return list<array{
     *     budget_id: int,
     *     cost_center_id: int|null,
     *     cost_center_code: string|null,
     *     account_prefix: string|null,
     *     label: string|null,
     *     budget: float,
     *     actual: float,
     *     remaining: float,
     *     consumption_rate: float,
     *     over_budget: bool
     * }>
     */
    public function budgetConsumption(string $companyId, int $year): array
    {
        $budgets = AccountingBudget::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->orderBy('cost_center_id')
            ->orderBy('account_prefix')
            ->get();

        $centers = AccountingCostCenter::query()
            ->where('company_id', $companyId)
            ->pluck('code', 'id');

        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $result = [];

        foreach ($budgets as $budget) {
            $query = DB::table('accounting_journal_entries')
                ->where('company_id', $companyId)
                ->whereBetween('entry_date', [$from, $to]);

            if ($budget->cost_center_id !== null) {
                $query->where('cost_center_id', $budget->cost_center_id);
            }

            if ($budget->account_prefix !== null && $budget->account_prefix !== '') {
                $query->where('account_code', 'like', $budget->account_prefix.'%');
            }

            $actual = round((float) $query->sum('debit'), 2);
            $amount = round((float) $budget->amount, 2);
            $remaining = round($amount - $actual, 2);

            $result[] = [
                'budget_id' => (int) $budget->id,
                'cost_center_id' => $budget->cost_center_id !== null ? (int) $budget->cost_center_id : null,
                'cost_center_code' => $budget->cost_center_id !== null
                    ? ($centers[$budget->cost_center_id] ?? null)
                    : null,
                'account_prefix' => $budget->account_prefix,
                'label' => $budget->label,
                'budget' => $amount,
                'actual' => $actual,
                'remaining' => $remaining,
                'consumption_rate' => $amount > 0.0 ? round($actual / $amount * 100, 2) : 0.0,
                'over_budget' => $remaining < 0.0,
            ];
        }

        return $result;
    }

    /**
     * Ventilation analytique d'une écriture : permet de rattacher a posteriori
     * des lignes à un axe (reclassement), sans réécrire les montants.
     *
     * @param  list<int>  $entryIds
     */
    public function assignCostCenter(string $companyId, array $entryIds, ?int $costCenterId): int
    {
        if ($entryIds === []) {
            return 0;
        }

        if ($costCenterId !== null) {
            $exists = AccountingCostCenter::query()
                ->where('company_id', $companyId)
                ->whereKey($costCenterId)
                ->exists();

            // Un axe d'une autre entreprise n'est jamais accepté.
            if (! $exists) {
                return 0;
            }
        }

        return DB::table('accounting_journal_entries')
            ->where('company_id', $companyId)
            ->whereIn('id', $entryIds)
            ->update(['cost_center_id' => $costCenterId]);
    }
}

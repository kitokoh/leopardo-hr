<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingDepreciation;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Amortissements linéaires (issue #9378).
 *
 * Le module n'avait aucune notion d'immobilisation : les achats d'actifs
 * restaient en charges de l'exercice et le résultat était surévalué du montant
 * des dotations non pratiquées.
 *
 * Invariants :
 *   - **idempotence** : une seule dotation par (immobilisation, période) ; une
 *     relance ne double jamais la charge ;
 *   - **plafonnement** : la dotation cumulée ne dépasse jamais la base
 *     amortissable (coût − valeur résiduelle), même avec des arrondis ;
 *   - **période clôturée** : aucune dotation ne peut être passée dans une
 *     période fermée (même garde que le posting documentaire) ;
 *   - **écriture équilibrée** : D 681 / C 28x, en devise de référence du tenant.
 */
class DepreciationService
{
    /**
     * Fin du mois d'une période `YYYY-MM`. Extraite parce que
     * `Carbon::createFromFormat()` est retourné nullable/false par son typage :
     * la garde ci-dessous rend le type certain pour l'appelant.
     */
    private function monthEnd(string $period): Carbon
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01');

        if (! $start instanceof Carbon) {
            throw new InvalidArgumentException('Période invalide : '.$period);
        }

        return $start->endOfMonth();
    }

    /**
     * Passe la dotation d'une période pour toutes les immobilisations actives
     * d'un tenant.
     *
     * @return array{posted: int, skipped: int, total: float}
     */
    public function runForPeriod(string $companyId, string $period): array
    {
        $this->assertPeriodOpen($period);

        $entryDate = $this->monthEnd($period);

        $assets = AccountingFixedAsset::query()
            ->where('company_id', $companyId)
            ->where('status', AccountingFixedAsset::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        $posted = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($assets as $asset) {
            $amount = $this->postForAsset($asset, $period, $entryDate);

            if ($amount === null) {
                $skipped++;

                continue;
            }

            $posted++;
            $total += $amount;
        }

        return ['posted' => $posted, 'skipped' => $skipped, 'total' => round($total, 2)];
    }

    /**
     * Dotation d'une immobilisation pour une période. `null` si rien à passer
     * (déjà dotée, hors période de service, totalement amortie, sortie).
     */
    public function postForAsset(AccountingFixedAsset $asset, string $period, ?Carbon $entryDate = null): ?float
    {
        $entryDate ??= $this->monthEnd($period);

        // Hors période de mise en service : pas de dotation avant l'utilisation.
        if ($entryDate->lt($asset->in_service_date->copy()->startOfMonth())) {
            return null;
        }

        // Déjà dotée : idempotence.
        $exists = AccountingDepreciation::query()
            ->where('fixed_asset_id', $asset->id)
            ->where('period', $period)
            ->exists();

        if ($exists) {
            return null;
        }

        $base = $asset->depreciableBase();
        if ($base <= 0.0) {
            return null;
        }

        $alreadyDepreciated = (float) AccountingDepreciation::query()
            ->where('fixed_asset_id', $asset->id)
            ->sum('amount');

        $remaining = round($base - $alreadyDepreciated, 2);
        if ($remaining <= 0.0) {
            return null;
        }

        // Dotation du mois, plafonnée par le reste à amortir : la dernière
        // période solde exactement la base, sans dépassement d'arrondi.
        //
        // `remaining <= monthly + 0.01` (et non `min()`) : sur 1 000 en 3 mois,
        // la dotation vaut 333,33 ; après deux mois il reste 333,34, soit un
        // centime de plus que la mensualité. `min()` laisserait ce centime non
        // amorti à jamais. La dernière période prend donc le reste dès qu'il
        // n'excède plus la mensualité (tolérance d'un centime d'arrondi).
        $monthly = $asset->monthlyDepreciation();
        $amount = $remaining <= $monthly + 0.01 ? $remaining : $monthly;
        $amount = round($amount, 2);

        if ($amount <= 0.0) {
            return null;
        }

        $accumulatedAfter = round($alreadyDepreciated + $amount, 2);
        $netBookValueAfter = round((float) $asset->acquisition_cost - $accumulatedAfter, 2);

        return DB::transaction(function () use ($asset, $period, $entryDate, $amount, $accumulatedAfter, $netBookValueAfter): float {
            $resolver = ChartAccountResolver::forCompany(
                \App\Core\Tenant\Domain\Models\Company::query()->findOrFail($asset->company_id)
            );

            $expense = $resolver->entry('depreciation_expense');
            $accumulated = $resolver->entry('accumulated_depreciation');

            $sourceId = (int) $asset->id;

            // Écriture : D 681 (dotation) / C 28x (amortissements cumulés).
            AccountingJournalEntry::query()->updateOrCreate(
                [
                    'company_id' => $asset->company_id,
                    'source_type' => 'depreciation',
                    'source_id' => $sourceId,
                    'account_code' => $expense['code'],
                    'line_no' => 1,
                ],
                [
                    'entry_date' => $entryDate,
                    'period' => $period,
                    'currency' => $asset->currency,
                    'amount_currency' => $asset->currency,
                    'exchange_rate' => 1,
                    'journal_code' => 'OD',
                    'account_label' => $expense['label'],
                    'debit' => $amount,
                    'credit' => 0.0,
                    'piece' => 'AMORT-'.$asset->code.'-'.$period,
                    'description' => 'Dotation aux amortissements '.$asset->code.' '.$period,
                ],
            );

            AccountingJournalEntry::query()->updateOrCreate(
                [
                    'company_id' => $asset->company_id,
                    'source_type' => 'depreciation',
                    'source_id' => $sourceId,
                    'account_code' => $accumulated['code'],
                    'line_no' => 2,
                ],
                [
                    'entry_date' => $entryDate,
                    'period' => $period,
                    'currency' => $asset->currency,
                    'amount_currency' => $asset->currency,
                    'exchange_rate' => 1,
                    'journal_code' => 'OD',
                    'account_label' => $accumulated['label'],
                    'debit' => 0.0,
                    'credit' => $amount,
                    'piece' => 'AMORT-'.$asset->code.'-'.$period,
                    'description' => 'Dotation aux amortissements '.$asset->code.' '.$period,
                ],
            );

            AccountingDepreciation::query()->create([
                'company_id' => $asset->company_id,
                'fixed_asset_id' => $asset->id,
                'period' => $period,
                'entry_date' => $entryDate,
                'amount' => $amount,
                'accumulated_after' => $accumulatedAfter,
                'net_book_value_after' => $netBookValueAfter,
                'currency' => $asset->currency,
                'journal_entry_source_id' => $sourceId,
            ]);

            return $amount;
        });
    }

    /**
     * Tableau d'amortissement prévisionnel d'une immobilisation — sert à
     * l'affichage et au contrôle, sans rien comptabiliser.
     *
     * @return list<array{period: string, amount: float, accumulated: float, net_book_value: float}>
     */
    public function schedule(AccountingFixedAsset $asset): array
    {
        $base = $asset->depreciableBase();
        $monthly = $asset->monthlyDepreciation();

        if ($base <= 0.0 || $monthly <= 0.0) {
            return [];
        }

        $rows = [];
        $accumulated = 0.0;
        $cursor = $asset->in_service_date->copy()->startOfMonth();

        for ($i = 0; $i < (int) $asset->useful_life_months; $i++) {
            $remaining = round($base - $accumulated, 2);
            if ($remaining <= 0.0) {
                break;
            }

            $amount = round($remaining <= $monthly + 0.01 ? $remaining : $monthly, 2);
            $accumulated = round($accumulated + $amount, 2);

            $rows[] = [
                'period' => $cursor->format('Y-m'),
                'amount' => $amount,
                'accumulated' => $accumulated,
                'net_book_value' => round((float) $asset->acquisition_cost - $accumulated, 2),
            ];

            $cursor = $cursor->addMonth();
        }

        return $rows;
    }

    private function assertPeriodOpen(string $period): void
    {
        $closed = AccountingClosedPeriod::query()
            ->where('period', $period)
            ->exists();

        if ($closed) {
            throw new PeriodClosedException($period);
        }
    }
}

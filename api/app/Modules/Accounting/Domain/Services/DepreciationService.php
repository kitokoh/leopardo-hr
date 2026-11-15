<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Services;

use App\Modules\Accounting\Domain\Models\AccountingDepreciationEntry;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service de calcul et gestion des amortissements (#9377).
 */
class DepreciationService
{
    /**
     * Calcule l'échéancier complet d'amortissement pour une immobilisation.
     *
     * @return Collection<int, array{period: string, amount: float, cumulative: float}>
     */
    public function plan(AccountingFixedAsset $asset): Collection
    {
        $depreciableAmount = $asset->getDepreciableAmount();
        $acquisitionDate = Carbon::parse($asset->acquisition_date);
        $schedule = collect();
        
        if ($asset->method === 'linear') {
            $schedule = $this->calculateLinearSchedule($asset, $depreciableAmount, $acquisitionDate);
        } elseif ($asset->method === 'declining') {
            // Pour le moment, on refuse explicitement declining si non implémenté par pays
            throw new \InvalidArgumentException('Méthode declining non implémentée pour ce pays');
        }

        return $schedule;
    }

    /**
     * Amortissement linéaire avec prorata temporis la première année.
     *
     * @return Collection<int, array{period: string, amount: float, cumulative: float}>
     */
    private function calculateLinearSchedule(
        AccountingFixedAsset $asset,
        float $depreciableAmount,
        Carbon $acquisitionDate
    ): Collection {
        $schedule = collect();
        $monthlyRate = $depreciableAmount / $asset->useful_life_months;
        $cumulative = 0;

        // Prorata temporis pour le premier mois (jours d'occupation)
        $firstMonthStart = $acquisitionDate->copy();
        $firstMonthEnd = $acquisitionDate->copy()->endOfMonth();
        $daysInMonth = $firstMonthStart->daysInMonth;
        $daysOccupied = $firstMonthEnd->diffInDays($firstMonthStart) + 1;
        $firstMonthAmount = round(($monthlyRate * $daysOccupied) / $daysInMonth, 2);

        $cumulative += $firstMonthAmount;
        $schedule->push([
            'period' => $firstMonthStart->format('Y-m'),
            'amount' => $firstMonthAmount,
            'cumulative' => $cumulative,
        ]);

        // Mois suivants (amortissement complet)
        $currentMonth = $firstMonthStart->copy()->addMonth();
        $remainingMonths = $asset->useful_life_months - 1;

        for ($i = 0; $i < $remainingMonths; $i++) {
            $amount = $monthlyRate;
            
            // Dernière ligne : ajuster pour tomber exactement sur le montant total
            if ($i === $remainingMonths - 1) {
                $amount = round($depreciableAmount - $cumulative, 2);
            } else {
                $amount = round($amount, 2);
            }

            $cumulative += $amount;

            $schedule->push([
                'period' => $currentMonth->format('Y-m'),
                'amount' => $amount,
                'cumulative' => round($cumulative, 2),
            ]);

            $currentMonth->addMonth();
        }

        return $schedule;
    }

    /**
     * Poste les dotations pour une période donnée (idempotent).
     *
     * Crée les écritures comptables via JournalPostingService.
     *
     * @param  string  $period  Format YYYY-MM
     * @return int Nombre de dotations postées
     */
    public function postPeriod(string $period): int
    {
        // Vérifier que la période n'est pas clôturée
        // TODO: Implémenter la vérification PeriodClosedException

        // Récupérer toutes les entrées planned pour cette période
        $entries = AccountingDepreciationEntry::query()
            ->where('period', $period)
            ->where('status', 'planned')
            ->with('fixedAsset')
            ->get();

        $posted = 0;

        foreach ($entries as $entry) {
            // Créer les écritures comptables
            // TODO: Appeler JournalPostingService avec source_type=depreciation
            // Débit: expense_account_code / Crédit: depreciation_account_code
            
            $entry->update([
                'status' => 'posted',
                'journal_entry_group' => 'DEP-' . $period . '-' . $entry->fixed_asset_id,
            ]);

            $posted++;
        }

        return $posted;
    }

    /**
     * Cession d'une immobilisation.
     *
     * Sort le brut, l'amortissement cumulé, et calcule le résultat de cession.
     */
    public function dispose(
        AccountingFixedAsset $asset,
        Carbon $disposalDate,
        float $proceeds
    ): void {
        // TODO: Implémenter la logique de cession
        // 1. Sortie du brut (compte d'immobilisation)
        // 2. Sortie de l'amortissement cumulé (compte 28)
        // 3. Résultat de cession (675/775 ou équivalent)

        $asset->update([
            'status' => 'disposed',
            'disposal_date' => $disposalDate,
            'disposal_proceeds' => $proceeds,
        ]);
    }

    /**
     * Génère/met à jour les entrées d'amortissement planned pour une immobilisation.
     */
    public function generateEntries(AccountingFixedAsset $asset): int
    {
        $schedule = $this->plan($asset);
        $created = 0;

        foreach ($schedule as $entry) {
            AccountingDepreciationEntry::query()->updateOrCreate(
                [
                    'company_id' => $asset->company_id,
                    'fixed_asset_id' => $asset->id,
                    'period' => $entry['period'],
                ],
                [
                    'amount' => $entry['amount'],
                    'cumulative_amount' => $entry['cumulative'],
                    'status' => 'planned',
                ]
            );
            $created++;
        }

        return $created;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelAccountingEntry;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Contrat Accounting de FuelStation (FUEL-015, issue #5809).
 *
 * Publie des AGRÉGATS VALIDÉS vers Accounting sous forme de lignes
 * d'écriture (partie double, équilibrées) — sans accès direct aux tables
 * Accounting et sans toucher au flux opérationnel :
 *  - ventes du jour par station (`FUEL-SALES-{station}-{day}`) ;
 *  - clôture de caisse validée (`FUEL-CASH-{session}`) ;
 *  - écart de stock rapproché (`FUEL-VAR-{station}-{day}-{product}`).
 *
 * Idempotence : UNIQUE (company_id, reference) — la régénération remplace
 * les lignes d'une même référence (rejouable, zéro doublon). Aucune donnée
 * sensible/PII dans les labels (références métier uniquement).
 */
final class FuelAccountingContractService
{
    /**
     * Génère (ou régénère) les lignes de ventes agrégées d'une station pour
     * un jour. Retourne le nombre de lignes persistées.
     */
    public function generateSalesEntries(string $companyId, int $stationId, string $day, ?Employee $actor = null): int
    {
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = (clone $dayStart)->addDay();

        $totals = FuelSale::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->whereBetween('sale_time', [$dayStart, $dayEnd])
            ->selectRaw('product, SUM(amount) as total_amount')
            ->groupBy('product')
            ->get();

        $lines = [];

        foreach ($totals as $total) {
            $amount = (float) $total->total_amount;

            if ($amount <= 0) {
                continue;
            }

            $reference = sprintf('FUEL-SALES-%d-%s', $stationId, $dayStart->toDateString());
            $productLabel = (string) $total->product;

            $lines[] = [
                'station_id' => $stationId,
                'period' => $dayStart->toDateString(),
                'entry_type' => FuelAccountingEntry::TYPE_SALES,
                'account_code' => '531000',
                'account_label' => 'Caisse — ventes carburant '.$productLabel,
                'debit' => $amount,
                'credit' => 0.0,
                'reference' => $reference,
            ];
            $lines[] = [
                'station_id' => $stationId,
                'period' => $dayStart->toDateString(),
                'entry_type' => FuelAccountingEntry::TYPE_SALES,
                'account_code' => '701100',
                'account_label' => 'Ventes de carburant — '.$productLabel,
                'debit' => 0.0,
                'credit' => $amount,
                'reference' => $reference,
            ];
        }

        return $this->persistLines($companyId, $lines, $actor);
    }

    /**
     * Génère (ou régénère) les lignes d'une clôture de caisse validée
     * (écart éventuel explicité — jamais silencieux).
     */
    public function generateCashSessionEntries(string $companyId, FuelCashSession $session, ?Employee $actor = null): int
    {
        $expected = (float) $session->expected_balance;
        $difference = (float) $session->variance;

        $reference = 'FUEL-CASH-'.$session->id;
        $lines = [];

        if ($expected > 0) {
            $lines[] = [
                'station_id' => $session->station_id,
                'period' => $session->closed_at?->toDateString() ?? Carbon::today()->toDateString(),
                'entry_type' => FuelAccountingEntry::TYPE_CASH_SESSION,
                'account_code' => '531000',
                'account_label' => 'Caisse — clôture de session',
                'debit' => $expected,
                'credit' => 0.0,
                'reference' => $reference,
            ];
            $lines[] = [
                'station_id' => $session->station_id,
                'period' => $session->closed_at?->toDateString() ?? Carbon::today()->toDateString(),
                'entry_type' => FuelAccountingEntry::TYPE_CASH_SESSION,
                'account_code' => '701100',
                'account_label' => 'Ventes encaissées — clôture de session',
                'debit' => 0.0,
                'credit' => $expected,
                'reference' => $reference,
            ];
        }

        if ($difference !== 0.0) {
            $isLoss = $difference < 0;
            $lines[] = [
                'station_id' => $session->station_id,
                'period' => $session->closed_at?->toDateString() ?? Carbon::today()->toDateString(),
                'entry_type' => FuelAccountingEntry::TYPE_CASH_SESSION,
                'account_code' => $isLoss ? '658000' : '758000',
                'account_label' => $isLoss ? 'Pertes sur caisse — écart de clôture' : 'Produits divers — écart de caisse',
                'debit' => $isLoss ? abs($difference) : 0.0,
                'credit' => $isLoss ? 0.0 : abs($difference),
                'reference' => $reference,
            ];
        }

        return $this->persistLines($companyId, $lines, $actor);
    }

    /**
     * Génère (ou régénère) la ligne d'écart de stock d'un rapprochement en
     * variance (jamais silencieux — l'écart est explicité).
     */
    public function generateStockVarianceEntries(string $companyId, FuelStockReconciliation $reconciliation, ?Employee $actor = null): int
    {
        $variance = (int) $reconciliation->variance_minor;

        if ($reconciliation->status !== FuelStockReconciliation::STATUS_VARIANCE || $variance === 0) {
            return 0;
        }

        $reference = sprintf(
            'FUEL-VAR-%d-%s-%s',
            $reconciliation->station_id,
            (string) $reconciliation->day,
            (string) $reconciliation->product_type
        );

        $lines = [
            [
                'station_id' => $reconciliation->station_id,
                'period' => (string) $reconciliation->day,
                'entry_type' => FuelAccountingEntry::TYPE_STOCK_VARIANCE,
                'account_code' => '603700',
                'account_label' => 'Variation de stocks — '.$reconciliation->product_type,
                'debit' => $variance > 0 ? (float) $variance : 0.0,
                'credit' => $variance < 0 ? (float) abs($variance) : 0.0,
                'reference' => $reference,
            ],
        ];

        return $this->persistLines($companyId, $lines, $actor);
    }

    /**
     * Synchronisation complète d'une station sur une période : ventes du jour,
     * clôtures de caisse sans écritures, écarts de stock en variance.
     *
     * @return int nombre total de lignes persistées
     */
    public function syncStation(string $companyId, int $stationId, string $day, ?Employee $actor = null): int
    {
        $count = 0;
        $count += $this->generateSalesEntries($companyId, $stationId, $day, $actor);

        $closedSessions = FuelCashSession::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->where('status', FuelCashSession::STATUS_CLOSED)
            ->whereNotNull('closed_at')
            ->get();

        foreach ($closedSessions as $session) {
            $reference = 'FUEL-CASH-'.$session->id;
            $hasEntries = FuelAccountingEntry::query()
                ->where('company_id', $companyId)
                ->where('reference', $reference)
                ->exists();

            if (! $hasEntries) {
                $count += $this->generateCashSessionEntries($companyId, $session, $actor);
            }
        }

        $variances = FuelStockReconciliation::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->where('day', $day)
            ->where('status', FuelStockReconciliation::STATUS_VARIANCE)
            ->get();

        foreach ($variances as $reconciliation) {
            $count += $this->generateStockVarianceEntries($companyId, $reconciliation, $actor);
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function persistLines(string $companyId, array $lines, ?Employee $actor = null): int
    {
        if ($lines === []) {
            return 0;
        }

        $createdBy = $actor?->id;

        // Idempotence : régénération = remplacement complet des lignes d'une
        // référence (suppression unique AVANT création, sinon les lignes
        // d'une même référence s'écraseraient entre elles).
        $references = collect($lines)
            ->pluck('reference')
            ->map(static fn (mixed $r): string => (string) $r)
            ->unique();

        foreach ($references as $reference) {
            FuelAccountingEntry::query()
                ->where('company_id', $companyId)
                ->where('reference', $reference)
                ->delete();
        }

        foreach ($lines as $line) {
            FuelAccountingEntry::query()->create(array_merge($line, [
                'company_id' => $companyId,
                'created_by' => $createdBy,
            ]));
        }

        return count($lines);
    }
}

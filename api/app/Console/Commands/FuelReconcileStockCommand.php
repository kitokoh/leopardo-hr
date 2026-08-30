<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\FuelStation\Application\Jobs\FuelReconciliationJob;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Console\Command;

/**
 * fuel:reconcile-stock — Rapprochement stock FuelStation (FUEL-009 #5803).
 *
 * Rejouable : le rapport est un updateOrCreate par (company, station, period).
 * Usage :
 *   php artisan fuel:reconcile-stock {station} {period} [--company=] [--sync]
 *   (--sync exécute en ligne au lieu de dispatcher le job)
 */
class FuelReconcileStockCommand extends Command
{
    protected $signature = 'fuel:reconcile-stock {station : station_id}
        {period : période YYYY-MM}
        {--company= : company_id (obligatoire hors contexte tenant)}
        {--sync : exécute en ligne au lieu de dispatcher le job}';

    protected $description = 'Calcule (ou rejoue) le rapport de rapprochement stock d\'une station pour une période (FUEL-009).';

    public function handle(FuelStockService $stocks): int
    {
        $stationId = (int) $this->argument('station');
        $period = (string) $this->argument('period');
        $companyId = (string) $this->option('company');

        if ($companyId === '') {
            $this->error('--company est requis hors contexte tenant.');

            return self::FAILURE;
        }

        if (! preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error("Période invalide : {$period} (attendu YYYY-MM).");

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $report = $stocks->reconcile($companyId, $stationId, $period);
            $this->info("[fuel:reconcile-stock] station #{$stationId} {$period} → {$report->status} (variance {$report->variance_liters} l).");

            return self::SUCCESS;
        }

        FuelReconciliationJob::dispatch($companyId, $stationId, $period);
        $this->info("[fuel:reconcile-stock] job FuelReconciliationJob dispatché (station #{$stationId}, {$period}).");

        return self::SUCCESS;
    }
}

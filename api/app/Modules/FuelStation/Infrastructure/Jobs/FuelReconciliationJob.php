<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Jobs;

use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rapprochement stock rejouable (FUEL-009, #5803).
 *
 * Relancer le job pour le même (company, station, date) recalcule et
 * REMPLACE le rapport du jour (UNIQUE (company_id, station_id,
 * report_date)) — zéro doublon, résultat déterministe. Contexte tenant
 * explicite (company_id) : pas de scope global requis hors requête.
 */
final class FuelReconciliationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly string $companyId,
        private readonly int $stationId,
        private readonly string $reportDate,
    ) {}

    public function handle(FuelStockService $stock): void
    {
        try {
            $stock->reconcileForCompany($this->companyId, $this->stationId, $this->reportDate);
        } catch (\Throwable $e) {
            // Ne jamais exposer de PII dans les logs (règle FUEL-020).
            Log::error('fuel.reconciliation.failed', [
                'company_id' => $this->companyId,
                'station_id' => $this->stationId,
                'report_date' => $this->reportDate,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

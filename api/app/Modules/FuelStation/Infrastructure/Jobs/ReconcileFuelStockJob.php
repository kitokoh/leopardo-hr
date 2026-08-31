<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Rapprochement stock FuelStation (FUEL-009, issue #5803) — rejouable.
 *
 * Exécute la passe de rapprochement d'une station pour une date. La
 * contrainte unique (company_id, station_id, run_date) rend le job
 * idempotent : un rejeu renvoie le run existant, jamais de doublon.
 * Tenant-scoped (EnsureTenantContext) : le job s'exécute dans le contexte
 * du tenant propriétaire de la station.
 */
final class ReconcileFuelStockJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $companyId,
        private readonly int $stationId,
        private readonly string $runDate,
        private readonly ?int $actorId = null,
    ) {
        $this->onQueue('reconciliation');
    }

    public function tenantCompanyId(): string
    {
        return $this->companyId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(FuelStockService $stocks, TenantManager $tenants): void
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);

        if (! $company instanceof Company || $company->status !== 'active') {
            return;
        }

        $tenants->withinTenant($company, function () use ($stocks): void {
            $station = FuelStation::query()
                ->where('company_id', $this->companyId)
                ->find($this->stationId);

            if (! $station instanceof FuelStation) {
                Log::warning('fuel.reconciliation.station_missing', [
                    'company_id' => $this->companyId,
                    'station_id' => $this->stationId,
                    'run_date' => $this->runDate,
                ]);

                return;
            }

            $actor = $this->actorId !== null
                ? Employee::query()->find($this->actorId)
                : null;

            $stocks->reconcile($station, $this->runDate, $actor);
        });
    }
}

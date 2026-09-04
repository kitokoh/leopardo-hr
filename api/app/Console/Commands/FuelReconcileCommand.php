<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * fuel:reconcile — rapprochement stock/compteurs/ventes par station et jour
 * (FUEL-009, issue #5803).
 *
 * Idempotent par construction : UNIQUE (company_id, station_id, run_date) —
 * relancer la commande pour la même station/date recalcule et écrase le
 * résumé, sans créer de doublon. Sans `--station`, toutes les stations du
 * tenant sont rapprochées pour la date demandée (défaut : hier, pour
 * couvrir une journée complète de ventes).
 *
 * Usage : php artisan fuel:reconcile --tenant=<company_uuid> [--station=<id>]
 *         [--date=YYYY-MM-DD]
 */
class FuelReconcileCommand extends Command
{
    protected $signature = 'fuel:reconcile
        {--tenant= : UUID du tenant (obligatoire)}
        {--station= : id de station (défaut : toutes les stations du tenant)}
        {--date= : date du rapprochement (défaut : hier)}';

    protected $description = 'Rapprochement FuelStation stock ↔ compteurs ↔ ventes, idempotent par station/jour.';

    public function __construct(
        private readonly FuelStockService $stocks,
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if (! is_string($tenantId) || $tenantId === '') {
            $this->error('[fuel:reconcile] --tenant est obligatoire.');

            return self::FAILURE;
        }

        /** @var Company|null $company */
        $company = Company::query()->where('id', $tenantId)->first();

        if (! $company instanceof Company) {
            $this->error("[fuel:reconcile] Tenant introuvable : {$tenantId}");

            return self::FAILURE;
        }

        $stationId = $this->option('station');
        $stationIdInt = is_numeric($stationId) ? (int) $stationId : null;

        $dateOption = $this->option('date');
        $date = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)
            : now()->subDay();

        $companyId = (string) $company->id;

        $processed = $this->tenants->withinTenant($company, function () use ($stationIdInt, $date, $companyId): int {
            $stationIds = $stationIdInt !== null
                ? [$stationIdInt]
                : $this->stationIdsForTenant($companyId);

            $count = 0;

            foreach ($stationIds as $station) {
                $result = $this->stocks->reconcile($companyId, $station, $date);

                $variances = $result['variances']['variances'];

                $this->info(sprintf(
                    '[fuel:reconcile] station=%s date=%s %s (%d écart(s))',
                    $station ?? 'ALL',
                    $date->toDateString(),
                    $result['variances']['explained'] ? 'OK' : 'ÉCARTS À EXPLIQUER',
                    count($variances),
                ));

                $count++;
            }

            return $count;
        });

        $this->info("[fuel:reconcile] {$processed} station(s) rapprochée(s) pour {$date->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int|null>
     */
    private function stationIdsForTenant(string $companyId): array
    {
        $rows = DB::table('fuel_stations')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        return $rows === [] ? [null] : array_map('intval', $rows);
    }
}

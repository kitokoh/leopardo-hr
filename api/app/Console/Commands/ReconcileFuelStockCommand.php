<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * leopardo:fuel:reconcile-stock — rapprochement de stock FuelStation
 * (FUEL-009, #5803).
 *
 * Pour chaque tenant ayant activé la solution `fuel_station`, calcule les
 * snapshots de rapprochement de la journée demandée (défaut : hier) pour
 * chaque station. Rejouable : l'upsert par clé unique
 * (company_id, station_id, product_type, day) garantit l'idempotence —
 * aucun doublon, un écart n'est jamais silencieux (status variance + notes).
 *
 * Usage :
 *   php artisan leopardo:fuel:reconcile-stock
 *   php artisan leopardo:fuel:reconcile-stock --day=2026-08-30 --company=<uuid>
 */
class ReconcileFuelStockCommand extends Command
{
    protected $signature = 'leopardo:fuel:reconcile-stock
        {--day= : jour de rapprochement (Y-m-d, défaut hier)}
        {--company= : UUID du tenant (défaut : tous les tenants actifs)}';

    protected $description = 'Rapprochement de stock FuelStation (FUEL-009) — snapshots idempotents par (station, produit, jour).';

    public function handle(FuelStockService $stock): int
    {
        $day = $this->option('day') !== null
            ? (string) $this->option('day')
            : Carbon::yesterday()->toDateString();

        $companies = Company::query()
            ->when($this->option('company') !== null, fn ($q) => $q->where('id', (string) $this->option('company')))
            ->get()
            ->filter(fn (Company $company): bool => (bool) ($company->features['fuel_station'] ?? false));

        $processed = 0;
        $snapshots = 0;

        foreach ($companies as $company) {
            $stations = FuelStation::query()
                ->where('company_id', $company->id)
                ->where('status', FuelStation::STATUS_ACTIVE)
                ->get();

            foreach ($stations as $station) {
                try {
                    $created = $stock->reconcile($company->id, (int) $station->id, $day);
                    $snapshots += count($created);
                    $processed++;
                } catch (\Throwable $exception) {
                    Log::warning('[leopardo:fuel:reconcile-stock] station ignorée', [
                        'company_id' => $company->id,
                        'station_id' => $station->id,
                        'day' => $day,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $this->info(sprintf(
            '[leopardo:fuel:reconcile-stock] %d station(s) traitées, %d snapshot(s) (%s).',
            $processed,
            $snapshots,
            $day
        ));

        return self::SUCCESS;
    }
}

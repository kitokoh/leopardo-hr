<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelAccountingContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * leopardo:fuel:accounting-sync — publication des agrégats FuelStation vers
 * Accounting (FUEL-015, #5809).
 *
 * Pour chaque tenant ayant activé `fuel_station`, pour chaque station active,
 * génère (ou régénère) les lignes d'écriture des ventes du jour, des
 * clôtures de caisse sans écritures et des écarts de stock rapprochés.
 * Idempotent : UNIQUE (company_id, reference) — un rejeu ne double rien.
 *
 * Usage :
 *   php artisan leopardo:fuel:accounting-sync
 *   php artisan leopardo:fuel:accounting-sync --day=2026-08-30 --company=<uuid>
 */
class SyncFuelAccountingCommand extends Command
{
    protected $signature = 'leopardo:fuel:accounting-sync
        {--day= : jour de publication (Y-m-d, défaut hier)}
        {--company= : UUID du tenant (défaut : tous les tenants actifs)}';

    protected $description = 'Publie les agrégats FuelStation (ventes, caisses, écarts) vers Accounting — idempotent.';

    public function handle(FuelAccountingContractService $contract): int
    {
        $day = $this->option('day') !== null
            ? (string) $this->option('day')
            : Carbon::yesterday()->toDateString();

        $companies = Company::query()
            ->when($this->option('company') !== null, fn ($q) => $q->where('id', (string) $this->option('company')))
            ->get()
            ->filter(fn (Company $company): bool => (bool) ($company->features['fuel_station'] ?? false));

        $lines = 0;
        $stations = 0;

        foreach ($companies as $company) {
            $companyStations = FuelStation::query()
                ->where('company_id', $company->id)
                ->where('status', FuelStation::STATUS_ACTIVE)
                ->get();

            foreach ($companyStations as $station) {
                try {
                    $lines += $contract->syncStation($company->id, (int) $station->id, $day);
                    $stations++;
                } catch (\Throwable $exception) {
                    Log::warning('[leopardo:fuel:accounting-sync] station ignorée', [
                        'company_id' => $company->id,
                        'station_id' => $station->id,
                        'day' => $day,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $this->info(sprintf(
            '[leopardo:fuel:accounting-sync] %d station(s), %d ligne(s) d\'écriture (%s).',
            $stations,
            $lines,
            $day
        ));

        return self::SUCCESS;
    }
}

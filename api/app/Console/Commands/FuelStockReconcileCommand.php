<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * fuel:stock-reconcile — Rapprochement de stock rejouable (FUEL-009, #5803).
 *
 * Pour chaque station active de chaque tenant, pour chaque produit actif,
 * exécute le rapprochement de la veille (ou de la date demandée). Rejouable
 * par construction : la clé d'idempotence `scheduled:{date}:{station}:{product}`
 * garantit qu'une seconde passe retourne les rapports existants.
 *
 * Usage : php artisan fuel:stock-reconcile [--date=YYYY-MM-DD] [--company=uuid]
 * Scheduler : quotidien (00:15).
 */
class FuelStockReconcileCommand extends Command
{
    protected $signature = 'fuel:stock-reconcile
        {--date= : date de rapprochement (défaut : hier)}
        {--company= : UUID du tenant (défaut : tous)}';

    protected $description = 'Exécute les rapprochements de stock FuelStation de la période (idempotent, rejouable).';

    public function __construct(
        private readonly FuelStockService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date');

        if (! is_string($date) || $date === '') {
            $date = now()->subDay()->toDateString();
        }

        $companyFilter = $this->option('company');
        $companies = Company::query()->where('features->fuel_station', true);

        if (is_string($companyFilter) && $companyFilter !== '') {
            $companies->where('id', $companyFilter);
        }

        $reconciled = 0;

        foreach ($companies->get() as $company) {
            try {
                $reconciled += DB::transaction(function () use ($company, $date): int {
                    $stations = FuelStation::query()
                        ->where('company_id', $company->id)
                        ->where('status', FuelStation::STATUS_ACTIVE)
                        ->get();

                    $products = FuelProduct::query()
                        ->where('company_id', $company->id)
                        ->where('status', FuelProduct::STATUS_ACTIVE)
                        ->get();

                    $ran = 0;

                    foreach ($stations as $station) {
                        foreach ($products as $product) {
                            $this->service->runReconciliation(
                                actor: null,
                                station: $station,
                                data: [
                                    'product_type' => (string) $product->code,
                                    'period_start' => $date,
                                    'period_end' => $date,
                                    'idempotency_key' => "scheduled:{$date}:{$station->id}:{$product->code}",
                                    // Période passée : pas de repli sur les niveaux
                                    // de cuves du jour (FUEL-009/C3).
                                    'fallback_to_tank_levels' => false,
                                ],
                            );
                            ++$ran;
                        }
                    }

                    return $ran;
                });
            } catch (Throwable $exception) {
                $this->error("Échec rapprochement tenant {$company->id} : {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info("Rapprochements exécutés : {$reconciled} (date {$date}).");

        return self::SUCCESS;
    }
}

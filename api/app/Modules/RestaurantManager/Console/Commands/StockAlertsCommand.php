<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Application\Services\StockAlertService;
use Illuminate\Console\Command;

/**
 * RESTO-505 (#6204) — Commande de détection des alertes de seuil de stock.
 *
 * `php artisan leopardo:restaurant:stock-alerts {company?}` : rescanne les
 * niveaux sous seuil de TOUS les tenants (ou d'un seul si fourni) et publie
 * `restaurant.stock.alert.v1` via l'outbox (idempotent : une alerte par
 * branche/ingrédient/jour — rejouer la commande ne spamme pas).
 */
final class StockAlertsCommand extends Command
{
    protected $signature = 'leopardo:restaurant:stock-alerts {company? : UUID of the company to scan (default: all)}';

    protected $description = 'Publie les alertes de seuil de stock RestaurantManager (restaurant.stock.alert.v1)';

    public function handle(TenantManager $tenants, StockAlertService $alerts): int
    {
        $companyId = $this->argument('company');

        if ($companyId !== null) {
            $company = Company::query()->find($companyId);

            if (! $company instanceof Company) {
                $this->error("Company {$companyId} introuvable.");

                return self::FAILURE;
            }

            $count = $tenants->withinTenant($company, fn (): int => $alerts->scan($company->id));
            $this->info("Alerts published for {$company->id}: {$count}.");

            return self::SUCCESS;
        }

        $total = 0;
        $companies = Company::query()->get(['id']);

        foreach ($companies as $company) {
            $count = $tenants->withinTenant($company, fn (): int => $alerts->scan($company->id));
            $total += $count;
        }

        $this->info("Alerts published for {$companies->count()} tenants: {$total} in total.");

        return self::SUCCESS;
    }
}

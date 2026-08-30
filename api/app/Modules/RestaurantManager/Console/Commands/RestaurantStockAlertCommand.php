<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantStockAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:restaurant:stock-alerts — Détection des alertes de seuil de stock.
 *
 * Usage :
 *   php artisan leopardo:restaurant:stock-alerts              # toutes les company actives
 *   php artisan leopardo:restaurant:stock-alerts {company}    # une company (id uuid ou slug)
 *
 * Publie un événement outbox `restaurant.stock.alert.v1` par
 * (branche, ingrédient, jour) franchissant le seuil — idempotent (RESTO-505).
 */
final class RestaurantStockAlertCommand extends Command
{
    protected $signature = 'leopardo:restaurant:stock-alerts
                            {company? : Company ID (uuid) or slug — scanne toutes les companies si absent}';

    protected $description = 'Publie les alertes de seuil de stock RestaurantManager (idempotent, une par jour).';

    public function __construct(
        private readonly RestaurantStockAlertService $alerts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $input = $this->argument('company');
        $companies = [];

        if ($input !== null && trim((string) $input) !== '') {
            $company = $this->resolveCompany(trim((string) $input));

            if (! $company instanceof Company) {
                $this->error("Company not found: {$input}");

                return self::FAILURE;
            }

            $companies = [$company];
        } else {
            $companies = Company::query()
                ->get()
                ->filter(fn (Company $company) => $company->hasFeature('restaurantmanager'))
                ->values()
                ->all();
        }

        $total = ['alerts_created' => 0, 'alerts_duplicates' => 0];

        foreach ($companies as $company) {
            $result = $this->alerts->scanCompany($company);
            $total['alerts_created'] += $result['alerts_created'];
            $total['alerts_duplicates'] += $result['alerts_duplicates'];

            $this->info(sprintf(
                '%s (%s) : %d alerte(s) créée(s), %d doublon(s) ignoré(s).',
                $company->name,
                $company->slug,
                $result['alerts_created'],
                $result['alerts_duplicates'],
            ));
        }

        $this->info(sprintf('Total : %d alerte(s) créée(s), %d doublon(s).', $total['alerts_created'], $total['alerts_duplicates']));

        return self::SUCCESS;
    }

    private function resolveCompany(string $input): ?Company
    {
        if (Str::isUuid($input)) {
            return Company::query()->where('id', $input)->first();
        }

        return Company::query()->where('slug', $input)->first();
    }
}

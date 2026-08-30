<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Console\Command;

/**
 * leopardo:travel:expire-adverts — Expiration des annonces publicitaires
 * (TRAVEL-908, issue #6111).
 *
 * Toute annonce `published` dont `valid_until` est dépassée passe
 * `expired` (invisible de la liste publique). Idempotent : un rejeu ne
 * retouche jamais une annonce déjà expirée.
 *
 * Usage : php artisan leopardo:travel:expire-adverts [--tenant=uuid]
 */
class ExpireTravelAdvertsCommand extends Command
{
    protected $signature = 'leopardo:travel:expire-adverts
        {--tenant= : id de tenant (défaut : tous les tenants avec activité travel)}';

    protected $description = 'Expire les annonces publicitaires TravelAgency dont la validité est dépassée (idempotent).';

    public function handle(TenantManager $tenants): int
    {
        $tenantId = $this->option('tenant');

        $companies = $tenantId !== null
            ? Company::query()->where('id', (string) $tenantId)->get()
            : Company::query()
                ->whereNotNull('schema_name')
                ->whereJsonContains('features', ['travelagency' => true])
                ->get();

        $total = 0;

        foreach ($companies as $company) {
            $count = $tenants->withinTenant($company, function (): int {
                $expired = TravelAdvert::query()
                    ->where('status', 'published')
                    ->where('valid_until', '<', now())
                    ->get();

                $affected = 0;

                foreach ($expired as $advert) {
                    $updated = TravelAdvert::query()
                        ->whereKey($advert->id)
                        ->where('status', 'published')
                        ->update(['status' => 'expired']);

                    $affected += $updated;
                }

                return $affected;
            });

            $total += $count;

            if ($count > 0) {
                $this->info(sprintf('  • %s : %d annonce(s) expirée(s).', $company->id, $count));
            }
        }

        $this->info(sprintf('Terminé — %d annonce(s) expirée(s).', $total));

        return self::SUCCESS;
    }
}

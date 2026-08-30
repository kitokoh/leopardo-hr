<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Console\Command;

/**
 * TRAVEL-908 (#6111) — Expiration des annonces.
 *
 * validated avec `expires_at` dépassé → expired (invisible). Idempotent :
 * une annonce déjà expirée n'est pas re-traitée ; reprise de commande sûre.
 */
class TravelExpireAdvertsCommand extends Command
{
    protected $signature = 'travel:expire-adverts
        {--company= : Cibler un tenant précis}
        {--limit=1000 : nombre max d\'annonces par passe (défaut 1000)}';

    protected $description = 'Expire les annonces validées dont expires_at est dépassé (TRAVEL-908/#6111).';

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucun tenant actif — rien à expirer.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $total = 0;

        foreach ($companies as $company) {
            $count = $tenantManager->withinTenant($company, function () use ($company, $limit): int {
                return TravelAdvert::query()
                    ->where('company_id', $company->id)
                    ->where('status', AdvertStatus::VALIDATED->value)
                    ->where('expires_at', '<=', now())
                    ->orderBy('id')
                    ->limit($limit)
                    ->update(['status' => AdvertStatus::EXPIRED->value]);
            });

            if ($count > 0) {
                $this->info("Tenant {$company->id} : {$count} annonce(s) expirée(s).");
            }
            $total += $count;
        }

        $this->info("Total : {$total} annonce(s) expirée(s).");

        return self::SUCCESS;
    }
}

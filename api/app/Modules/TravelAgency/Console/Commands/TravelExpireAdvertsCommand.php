<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Console\Command;

/**
 * TRAVEL-908 (#6111) — Expiration et archivage des annonces.
 *
 * - `validated` avec `expires_at` dépassé → `expired` (l'annonce n'est plus
 *   visible) ;
 * - `expired` dont `expires_at` remonte à plus de ARCHIVE_AFTER_DAYS → `archived`
 *   (nettoyage du cycle de vie).
 *
 * Idempotent : une annonce déjà expirée/archivée n'est pas re-traitée ; reprise
 * de commande sûre. Tenant-scopé (le trait `BelongsToCompany` + `withinTenant`
 * garantissent qu'aucune ligne d'un autre tenant n'est touchée), filtrable par
 * `--company` — c'est l'option utilisée par les tests et le runbook pilote.
 */
class TravelExpireAdvertsCommand extends Command
{
    /** Archivage d'une annonce expirée après N jours. */
    public const ARCHIVE_AFTER_DAYS = 90;

    protected $signature = 'travel:expire-adverts
        {--company= : Cibler un tenant précis}
        {--limit=1000 : nombre max d\'annonces par passe (défaut 1000)}';

    protected $description = 'Expire les annonces validées dont expires_at est dépassé, puis archive les expirées anciennes (TRAVEL-908/#6111).';

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucun tenant — rien à expirer.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $expiredTotal = 0;
        $archivedTotal = 0;

        foreach ($companies as $company) {
            $result = $tenantManager->withinTenant($company, function () use ($company, $limit): array {
                $expired = TravelAdvert::query()
                    ->where('company_id', $company->id)
                    ->where('status', AdvertStatus::VALIDATED->value)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->orderBy('id')
                    ->limit($limit)
                    ->update(['status' => AdvertStatus::EXPIRED->value, 'updated_at' => now()]);

                $archived = TravelAdvert::query()
                    ->where('company_id', $company->id)
                    ->where('status', AdvertStatus::EXPIRED->value)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now()->subDays(self::ARCHIVE_AFTER_DAYS))
                    ->orderBy('id')
                    ->limit($limit)
                    ->update(['status' => AdvertStatus::ARCHIVED->value, 'updated_at' => now()]);

                return ['expired' => $expired, 'archived' => $archived];
            });

            if ($result['expired'] > 0 || $result['archived'] > 0) {
                $this->info("Tenant {$company->id} : {$result['expired']} expirée(s), {$result['archived']} archivée(s).");
            }

            $expiredTotal += $result['expired'];
            $archivedTotal += $result['archived'];
        }

        $this->info("Total : {$expiredTotal} annonce(s) expirée(s), {$archivedTotal} archivée(s).");

        return self::SUCCESS;
    }
}

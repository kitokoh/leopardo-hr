<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExpirePendingBookingsJob;
use Illuminate\Console\Command;

/**
 * TRAVEL-418 (#6070) — Balaye les réservations `pending` expirées.
 *
 * Liste les compagnies ayant au moins une réservation `pending` dont
 * `expires_at` est dépassé (requête hors contexte tenant, `withoutGlobalScopes`),
 * puis dispatch un `ExpirePendingBookingsJob` tenant-scoped par compagnie
 * (contexte établi par `EnsureTenantContext`). `--sync` exécute l'expiration
 * en ligne (utile en test ou dans les environnements sans worker de queue).
 *
 * Scheduler : toutes les 5 minutes (bootstrap/app.php) — les sièges des
 * réservations jamais payées sont libérés au plus tard 5 min après
 * l'échéance, sans intervention manuelle.
 */
class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'travel:expire-pending-bookings
        {--sync : exécute l\'expiration en ligne (sans file de queue)}
        {--limit=500 : nombre maximal de compagnies traitées par passe}';

    protected $description = 'Expire les réservations TravelAgency pending dont expires_at est dépassé (TRAVEL-418/#6070).';

    public function handle(TenantManager $tenants): int
    {
        /** @var list<string> $companyIds */
        $companyIds = TravelBooking::query()
            ->withoutGlobalScopes()
            ->where('status', BookingStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->all();

        if ($companyIds === []) {
            $this->info('Aucune réservation pending expirée.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $targets = array_slice($companyIds, 0, $limit);

        $this->info(sprintf(
            '%d compagnie(s) concernée(s) (%d traitées, limit=%d).',
            count($companyIds),
            count($targets),
            $limit,
        ));

        foreach ($targets as $companyId) {
            if ($this->option('sync')) {
                $this->expireInline($tenants, $companyId);
                $this->line("  [sync] {$companyId} expirée en ligne.");

                continue;
            }

            dispatch(new ExpirePendingBookingsJob($companyId));
            $this->line("  [queued] {$companyId} → ExpirePendingBookingsJob.");
        }

        return self::SUCCESS;
    }

    private function expireInline(TenantManager $tenants, string $companyId): void
    {
        /** @var Company $company */
        $company = Company::query()->findOrFail($companyId);

        $tenants->withinTenant($company, static function () use ($companyId): void {
            dispatch_sync(new ExpirePendingBookingsJob($companyId));
        });
    }
}

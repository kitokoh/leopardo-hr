<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\TravelBookingExpirationService;
use Illuminate\Console\Command;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending (libère les
 * sièges des non-payés, spec §6.1 D4).
 *
 * Itère tous les tenants actifs (pattern RecalculateTravelReadModelsCommand)
 * et expire les réservations `pending` dont `expires_at` est dépassé —
 * idempotent : seule une réservation `pending` est traitée (rejeu sûr).
 *
 * Usage : php artisan travel:expire-pending-bookings --limit=200
 */
class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'travel:expire-pending-bookings
        {--company= : Cibler un tenant précis}
        {--limit=500 : nombre max de réservations par tenant et par passe}';

    protected $description = 'Expire les réservations travel pending dues et libère leurs sièges (TRAVEL-418).';

    public function handle(TenantManager $tenantManager, TravelBookingExpirationService $service): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No active company — nothing to expire.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $total = 0;

        foreach ($companies as $company) {
            $count = $tenantManager->withinTenant(
                $company,
                fn (): int => $service->expireDue($limit),
            );

            if ($count > 0) {
                $this->info("Tenant {$company->id}: {$count} réservation(s) expirée(s).");
            }
            $total += $count;
        }

        $this->info("Total: {$total} réservation(s) expirée(s).");

        return self::SUCCESS;
    }
}

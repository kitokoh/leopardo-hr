<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExpirePendingBookingsJob;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * UNE seule implémentation pour `travel:expire-pending-bookings` (consolidation
 * #8004) : l'ancien doublon racine `App\Console\Commands\ExpirePendingBookingsCommand`
 * et cette commande portaient le MÊME nom, donc un comportement dépendant de
 * l'ordre d'enregistrement des providers :
 *
 *  - `--company=<uuid|slug>` : expiration EN LIGNE d'un seul tenant — statut →
 *    cancelled, sièges libérés (`free`, `reserved_until` null) et événement
 *    `travel.booking.cancelled.v1` (motif `expired`) publié après commit,
 *    transaction + relecture `lockForUpdate` par réservation (idempotent) ;
 *  - `--sync` : expiration EN LIGNE de toutes les compagnies concernées via
 *    `ExpirePendingBookingsJob` (environnements sans worker de queue) ;
 *  - défaut : un `ExpirePendingBookingsJob` tenant-scoped par compagnie
 *    concernée (comportement historique du scheduler).
 *
 * Dans les modes globaux (`--sync` / défaut), `--limit` borne le nombre de
 * COMPAGNIES traitées par passe ; en mode `--company`, il borne le nombre de
 * RÉSERVATIONS expirées pour ce tenant.
 */
class TravelExpirePendingBookingsCommand extends Command
{
    protected $signature = 'travel:expire-pending-bookings
        {--company= : Cibler un tenant précis (UUID ou slug)}
        {--sync : exécute l\'expiration EN LIGNE (sans file de queue) — compatibilité #6070}
        {--limit=500 : nombre max de cibles par passe (compagnies en mode global, réservations en mode --company)}';

    protected $description = 'Expire les réservations pending dépassées : annulation + libération des sièges + événement (TRAVEL-418/#6070).';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
        parent::__construct();
    }

    public function handle(TenantManager $tenantManager): int
    {
        $companyOption = $this->option('company');
        $limit = max(1, (int) $this->option('limit'));

        if (is_string($companyOption) && trim($companyOption) !== '') {
            return $this->expireSingleTenant($tenantManager, trim($companyOption), $limit);
        }

        $companyIds = $this->companiesWithDueBookings();

        if ($companyIds === []) {
            $this->info('Aucune réservation pending expirée.');

            return self::SUCCESS;
        }

        $targets = array_slice($companyIds, 0, $limit);

        $this->info(sprintf(
            '%d compagnie(s) concernée(s) (%d traitées, limit=%d).',
            count($companyIds),
            count($targets),
            $limit,
        ));

        foreach ($targets as $companyId) {
            if ($this->option('sync')) {
                $this->expireInline($tenantManager, $companyId);
                $this->line("  [sync] {$companyId} expirée en ligne.");

                continue;
            }

            dispatch(new ExpirePendingBookingsJob($companyId));
            $this->line("  [queued] {$companyId} → ExpirePendingBookingsJob.");
        }

        return self::SUCCESS;
    }

    /**
     * Mode `--company` : expiration en ligne d'UN tenant (sièges + outbox).
     */
    private function expireSingleTenant(TenantManager $tenantManager, string $identifier, int $limit): int
    {
        $company = $this->resolveCompany($identifier);

        if (! $company instanceof Company) {
            $this->error("Company introuvable : {$identifier}");

            return self::FAILURE;
        }

        $count = $tenantManager->withinTenant(
            $company,
            fn (): int => $this->expireTenant((string) $company->id, $limit),
        );

        if ($count > 0) {
            $this->info("Tenant {$company->id} : {$count} réservation(s) expirée(s).");
        }

        $this->info("Total : {$count} réservation(s) expirée(s).");

        return self::SUCCESS;
    }

    /**
     * Compagnies ayant au moins une réservation pending dépassée (lecture hors
     * contexte tenant : `withoutGlobalScopes`, aucune écriture).
     *
     * @return list<string>
     */
    private function companiesWithDueBookings(): array
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

        return $companyIds;
    }

    private function expireInline(TenantManager $tenants, string $companyId): void
    {
        /** @var Company $company */
        $company = Company::query()->findOrFail($companyId);

        $tenants->withinTenant($company, static function () use ($companyId): void {
            dispatch_sync(new ExpirePendingBookingsJob($companyId));
        });
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (Str::isUuid($identifier)) {
            /** @var Company|null $byId */
            $byId = Company::query()->where('id', $identifier)->first();

            if ($byId instanceof Company) {
                return $byId;
            }
        }

        /** @var Company|null $bySlug */
        $bySlug = Company::query()->where('slug', $identifier)->first();

        return $bySlug;
    }

    private function expireTenant(string $companyId, int $limit): int
    {
        $expired = TravelBooking::query()
            ->where('company_id', $companyId)
            ->where('status', BookingStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($expired as $booking) {
            try {
                $this->expireOne($booking);
                $count++;
            } catch (Throwable $e) {
                // Une réservation en échec ne bloque pas le reste du lot ;
                // la prochaine passe la retentera (idempotence).
                $this->error("[travel:expire-pending-bookings] #{$booking->id} : {$e->getMessage()}");
            }
        }

        return $count;
    }

    private function expireOne(TravelBooking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            // Re-vérification sous transaction : un autre worker a pu
            // confirmer/annuler entre la sélection et le verrouillage.
            $fresh = TravelBooking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh instanceof TravelBooking || $fresh->status !== BookingStatus::PENDING) {
                return;
            }

            $fresh->forceFill([
                'status' => BookingStatus::CANCELLED,
                'expires_at' => null,
                'version' => $fresh->version + 1,
            ])->save();

            TravelTripSeat::query()
                ->where('trip_id', $fresh->trip_id)
                ->where('booking_id', $fresh->id)
                ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.cancelled.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'cancelled_by' => null,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => 'expired',
        ]);
    }
}

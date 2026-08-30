<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * Une réservation pending dont `expires_at` est dépassé est annulée :
 * statut → cancelled, sièges libérés (`free`, `reserved_until` null) et
 * événement `travel.booking.cancelled.v1` (motif `expired`) publié après
 * commit. Idempotent : une réservation déjà annulée n'est jamais re-traitée ;
 * reprise de commande sûre (transaction par réservation, log borné).
 */
class TravelExpirePendingBookingsCommand extends Command
{
    protected $signature = 'travel:expire-pending-bookings
        {--company= : Cibler un tenant précis}
        {--limit=500 : nombre max de réservations par passe (défaut 500)}';

    protected $description = 'Expire les réservations pending dépassées : annulation + libération des sièges + événement (TRAVEL-418/#6070).';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
        parent::__construct();
    }

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
            $count = $tenantManager->withinTenant(
                $company,
                fn (): int => $this->expireTenant((string) $company->id, $limit),
            );

            if ($count > 0) {
                $this->info("Tenant {$company->id} : {$count} réservation(s) expirée(s).");
            }
            $total += $count;
        }

        $this->info("Total : {$total} réservation(s) expirée(s).");

        return self::SUCCESS;
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

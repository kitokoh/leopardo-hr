<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * Job tenant-scoped, idempotent : pour chaque réservation `pending` dont
 * `expires_at` est dépassée, libère les sièges réservés et publie
 * l'événement `travel.booking.expired.v1`. Un rejeu ne touche jamais deux
 * fois la même réservation (transition pending → cancelled atomique).
 */
final class ExpireBookingsAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @return int Nombre de réservations expirées
     */
    public function execute(int $limit = 100): int
    {
        $expired = TravelBooking::query()
            ->where('status', BookingStatus::PENDING)
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($expired as $booking) {
            $updated = DB::transaction(function () use ($booking): bool {
                // Claim atomique : seules les réservations encore pending
                // (et non déjà traitées par un rejeu) sont expirées.
                $affected = TravelBooking::query()
                    ->whereKey($booking->id)
                    ->where('status', BookingStatus::PENDING)
                    ->update([
                        'status' => BookingStatus::CANCELLED,
                        'expires_at' => null,
                        'version' => $booking->version + 1,
                    ]);

                if ($affected === 0) {
                    return false;
                }

                TravelTripSeat::query()
                    ->where('trip_id', $booking->trip_id)
                    ->where('booking_id', $booking->id)
                    ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);

                return true;
            });

            if (! $updated) {
                continue;
            }

            $this->outbox->publish($booking->company_id, 'travel.booking.expired.v1', [
                'booking_reference' => $booking->reference,
                'trip_id' => $booking->trip_id,
                'expired_at' => now()->toIso8601String(),
            ]);

            $count++;
        }

        return $count;
    }
}

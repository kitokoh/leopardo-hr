<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TRAVEL-418 (#6070) — expiration des réservations pending.
 *
 * Une réservation `pending` dont `expires_at` est dépassé est annulée : les
 * sièges réservés sont libérés (`free`) et l'événement versionné
 * `travel.booking.expired.v1` est publié APRÈS le commit (idempotent — seule
 * une réservation `pending` est traitée ; un rejeu du job ne libère jamais
 * deux fois les sièges).
 */
final class TravelBookingExpirationService
{
    public const EVENT_BOOKING_EXPIRED = 'travel.booking.expired.v1';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
    }

    /**
     * Expire les réservations pending dues du tenant courant.
     *
     * @param  int|null  $limit  nombre max de réservations par passe
     * @return int nombre de réservations expirées
     */
    public function expireDue(?int $limit = null): int
    {
        $query = TravelBooking::query()
            ->where('status', BookingStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<TravelBooking> $due */
        $due = $query->get()->all();

        $expired = 0;

        foreach ($due as $booking) {
            if ($this->expireOne($booking)) {
                $expired++;
            }
        }

        return $expired;
    }

    private function expireOne(TravelBooking $booking): bool
    {
        // Re-vérification atomique : seule une réservation toujours pending
        // est expirée (idempotence en cas de rejeu/concurrence).
        $updated = DB::table('travel_bookings')
            ->where('id', $booking->getAttribute('id'))
            ->where('status', BookingStatus::PENDING->value)
            ->update([
                'status' => BookingStatus::CANCELLED->value,
                'expires_at' => null,
                'version' => $booking->version + 1,
            ]);

        if ($updated !== 1) {
            return false;
        }

        $released = DB::table('travel_trip_seats')
            ->where('trip_id', $booking->trip_id)
            ->where('booking_id', $booking->getAttribute('id'))
            ->update([
                'status' => SeatStatus::FREE->value,
                'reserved_until' => null,
            ]);

        $this->outbox->publish((string) $booking->company_id, self::EVENT_BOOKING_EXPIRED, [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'expired_at' => now()->toIso8601String(),
            'seats_released' => $released,
        ], 'expire-'.(int) $booking->getAttribute('id'));

        Log::channel('structured')->info('travel.booking.expired', [
            'booking_id' => (int) $booking->getAttribute('id'),
            'booking_reference' => $booking->reference,
            'company_id' => $booking->company_id,
            'seats_released' => $released,
        ]);

        return true;
    }
}

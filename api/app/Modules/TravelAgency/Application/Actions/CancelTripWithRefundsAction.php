<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-812 (#6102) — Annulation d'un trajet par l'agence (gestion
 * d'incident).
 *
 * Annule le trajet puis traite TOUTES ses réservations :
 *   - réservations confirmées → remboursement intégral automatique
 *     (pénalités neutralisées : c'est une annulation imputable à l'agence)
 *     + événements `travel.payment.refunded.v1` / `travel.booking.cancelled.v1`
 *     → les consommateurs (notifications TRAVEL-415, webhooks TRAVEL-806)
 *     préviennent les voyageurs ;
 *   - réservations pending → annulées, sièges libérés ;
 *   - sièges sold → libérés.
 * Idempotence : trajet déjà annulé → aucun effet de bord (rejeu sûr).
 */
final class CancelTripWithRefundsAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @return array{cancelled_bookings: int, refunded_bookings: int}
     */
    public function execute(TravelTrip $trip, Employee $actor, string $reason): array
    {
        if ($trip->status === TripStatus::CANCELLED) {
            return ['cancelled_bookings' => 0, 'refunded_bookings' => 0]; // Idempotence.
        }

        $stats = DB::transaction(function () use ($trip, $reason): array {
            $trip->forceFill(['status' => TripStatus::CANCELLED])->save();

            $refunded = 0;
            $cancelled = 0;

            /** @var list<TravelBooking> $bookings */
            $bookings = TravelBooking::query()
                ->where('trip_id', $trip->id)
                ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                ->get();

            foreach ($bookings as $booking) {
                if ($booking->status === BookingStatus::CONFIRMED) {
                    $booking->forceFill([
                        'status' => BookingStatus::REFUNDED,
                        'payment_status' => PaymentStatus::REFUNDED,
                        'version' => $booking->version + 1,
                    ])->save();

                    TravelPayment::query()
                        ->where('booking_id', $booking->id)
                        ->where('status', PaymentStatus::CONFIRMED)
                        ->update(['status' => PaymentStatus::REFUNDED]);

                    $refunded++;
                } else {
                    $booking->forceFill([
                        'status' => BookingStatus::CANCELLED,
                        'cancelled_at' => now(),
                        'cancel_reason' => $reason,
                        'expires_at' => null,
                        'version' => $booking->version + 1,
                    ])->save();

                    $cancelled++;
                }

                TravelTripSeat::query()
                    ->where('trip_id', $trip->id)
                    ->where('booking_id', $booking->id)
                    ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);
            }

            return ['cancelled_bookings' => $cancelled, 'refunded_bookings' => $refunded];
        });

        // Événement d'annulation du trajet APRÈS commit.
        $this->outbox->publish($trip->company_id, 'travel.trip.cancelled.v1', [
            'trip_id' => $trip->id,
            'trip_code' => $trip->code,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => $reason,
            'refunded_bookings' => $stats['refunded_bookings'],
            'cancelled_bookings' => $stats['cancelled_bookings'],
        ]);

        // Notifications voyageurs (TRAVEL-415) via les événements métier
        // publiés ci-dessus : chaque réservation confirmée émet
        // `travel.payment.refunded.v1` (les pending, `travel.booking.cancelled.v1`).
        $this->publishBookingEvents($trip, $actor, $reason);

        return $stats;
    }

    private function publishBookingEvents(TravelTrip $trip, Employee $actor, string $reason): void
    {
        /** @var list<TravelBooking> $bookings */
        $bookings = TravelBooking::query()
            ->where('trip_id', $trip->id)
            ->whereIn('status', [BookingStatus::REFUNDED, BookingStatus::CANCELLED])
            ->get();

        foreach ($bookings as $booking) {
            $this->outbox->publish($booking->company_id, 'travel.booking.cancelled.v1', [
                'booking_reference' => $booking->reference,
                'trip_id' => $trip->id,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now()->toIso8601String(),
                'reason' => $reason,
                'agency_cancellation' => true,
            ]);

            if ($booking->status === BookingStatus::REFUNDED) {
                $this->outbox->publish($booking->company_id, 'travel.payment.refunded.v1', [
                    'booking_reference' => $booking->reference,
                    'amount_minor' => $booking->total_amount_minor,
                    'penalty_minor' => 0,
                    'currency' => $booking->currency,
                    'partial' => false,
                    'refunded_by' => $actor->id,
                    'refunded_at' => now()->toIso8601String(),
                    'reason' => $reason,
                ]);
            }
        }
    }
}

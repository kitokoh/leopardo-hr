<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-314 (#6044) — Annulation d'une reservation.
 *
 * pending|confirmed → cancelled : les sieges sont liberes (`free`),
 * evenement outbox `travel.booking.cancelled.v1` apres commit. Le motif
 * est obligatoire (Request) et conserve dans le payload (audit). Une
 * reservation deja annulee est idempotente.
 */
final class CancelBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelBooking $booking, Employee $actor, string $reason): TravelBooking
    {
        if ($booking->status === BookingStatus::CANCELLED) {
            return $booking;
        }

        if (! in_array($booking->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            abort(422, 'Cette reservation ne peut plus etre annulee.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill([
                'status' => BookingStatus::CANCELLED,
                'expires_at' => null,
                'version' => $booking->version + 1,
            ])->save();

            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.cancelled.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => $reason,
        ]);

        return $booking->refresh()->load('passengers');
    }
}

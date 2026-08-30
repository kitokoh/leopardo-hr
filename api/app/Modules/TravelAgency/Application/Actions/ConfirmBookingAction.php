<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-313 (#6043) — Confirmation d'une réservation (comptant guichet).
 *
 * pending → confirmed : les sièges réservés passent `sold` (plus jamais
 * libérés par l'expiration), le paiement passe `confirmed` (cash),
 * événement outbox `travel.booking.confirmed.v1` après commit. Une
 * réservation déjà confirmée est idempotente.
 */
final class ConfirmBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelBooking $booking, Employee $actor): TravelBooking
    {
        if ($booking->status === BookingStatus::CONFIRMED) {
            return $booking;
        }

        if ($booking->status !== BookingStatus::PENDING) {
            abort(422, 'Seule une réservation en attente peut être confirmée.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill([
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => PaymentStatus::CONFIRMED,
                'expires_at' => null,
                'version' => $booking->version + 1,
            ])->save();

            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->update(['status' => SeatStatus::SOLD]);
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.confirmed.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return $booking->refresh()->load('passengers');
    }
}

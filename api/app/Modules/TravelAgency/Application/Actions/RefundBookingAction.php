<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-315 (#6045) — Remboursement d'une reservation confirmee.
 *
 * confirmed → refunded : les sieges sont liberes, le paiement passe
 * `refunded`, evenements outbox `travel.booking.cancelled.v1` +
 * `travel.payment.refunded.v1` apres commit. Reserve `travel.manage`
 * (Policy) ; motif et audit obligatoires. Une reservation deja remboursee
 * est idempotente.
 */
final class RefundBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelBooking $booking, Employee $actor, string $reason): TravelBooking
    {
        if ($booking->status === BookingStatus::REFUNDED) {
            return $booking;
        }

        if ($booking->status !== BookingStatus::CONFIRMED) {
            abort(422, 'Seule une reservation confirmee peut etre remboursee.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill([
                'status' => BookingStatus::REFUNDED,
                'payment_status' => PaymentStatus::REFUNDED,
                'version' => $booking->version + 1,
            ])->save();

            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);

            // Trace du paiement rembourse (audit financier).
            TravelPayment::query()
                ->where('booking_id', $booking->id)
                ->where('status', PaymentStatus::CONFIRMED)
                ->update(['status' => PaymentStatus::REFUNDED]);
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.cancelled.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => $reason,
            'notification_intent' => 'travel.booking.cancelled',
            'consent' => false, // Opt-in explicite requis via contrat CRM client (TRAVEL-416).
        ]);

        $this->outbox->publish($booking->company_id, 'travel.payment.refunded.v1', [
            'booking_reference' => $booking->reference,
            'amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'refunded_by' => $actor->id,
            'refunded_at' => now()->toIso8601String(),
            'notification_intent' => 'travel.payment.refunded',
            'consent' => false, // Opt-in explicite requis via contrat CRM client (TRAVEL-416).
        ]);

        return $booking->refresh()->load('passengers');
    }
}

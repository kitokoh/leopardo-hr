<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\RentalBookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-320 (#6050) — Annulation d'une réservation de location.
 *
 * pending|confirmed → cancelled, événement outbox
 * `travel.rental.booking.cancelled.v1` après commit. Idempotent.
 */
final class CancelRentalBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelRentalBooking $booking, Employee $actor, string $reason): TravelRentalBooking
    {
        if ($booking->status === RentalBookingStatus::CANCELLED) {
            return $booking;
        }

        if (! in_array($booking->status, [RentalBookingStatus::PENDING, RentalBookingStatus::CONFIRMED], true)) {
            abort(422, 'Cette réservation de location ne peut plus être annulée.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill(['status' => RentalBookingStatus::CANCELLED])->save();
        });

        $this->outbox->publish($booking->company_id, 'travel.rental.booking.cancelled.v1', [
            'rental_reference' => $booking->reference,
            'vehicle_id' => $booking->vehicle_id,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
            'reason' => $reason,
        ]);

        return $booking->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\RentalBookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-320 (#6050) — Reservation de location de vehicule.
 *
 * Invariant de non-chevauchement : deux reservations du meme vehicule ne
 * peuvent pas se chevaucher (scope `overlapping()`, verifie en transaction
 * avec verrouillage du vehicule — 409 sinon). Montant calcule cote serveur
 * (prix/jour × duree), idempotence par `idempotency_key`, evenement outbox
 * `travel.rental.booking.confirmed.v1` apres commit.
 */
final class CreateRentalBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  array{start_date: string, end_date: string, deposit_amount_minor?: int|null, customer_contact_id?: int|null, notes?: string|null}  $data
     */
    public function execute(
        TravelRentalVehicle $vehicle,
        Employee $actor,
        string $idempotencyKey,
        array $data,
    ): TravelRentalBooking {
        $existing = TravelRentalBooking::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof TravelRentalBooking) {
            return $existing;
        }

        $booking = DB::transaction(function () use ($vehicle, $idempotencyKey, $data): TravelRentalBooking {
            // Verrouille le vehicule : serialise les creations concurrentes.
            TravelRentalVehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            $overlap = TravelRentalBooking::query()
                ->overlapping($vehicle->id, $data['start_date'], $data['end_date'])
                ->exists();

            if ($overlap) {
                abort(409, 'Ce vehicule est deja reserve sur cette periode.');
            }

            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);
            $days = max(1, (int) $start->diffInDays($end) + 1);

            return TravelRentalBooking::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_contact_id' => $data['customer_contact_id'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount_minor' => $vehicle->price_per_day_minor * $days,
                'currency' => $vehicle->currency,
                'deposit_amount_minor' => $data['deposit_amount_minor'] ?? null,
                'payment_status' => PaymentStatus::PENDING,
                'status' => RentalBookingStatus::PENDING,
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        $this->outbox->publish($booking->company_id, 'travel.rental.booking.pending.v1', [
            'rental_reference' => $booking->reference,
            'vehicle_id' => $booking->vehicle_id,
            'start_date' => $booking->start_date->toDateString(),
            'end_date' => $booking->end_date->toDateString(),
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
        ]);

        return $booking;
    }
}

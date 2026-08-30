<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-802 (#6093) — Création d'un aller-retour combiné.
 *
 * Crée deux réservations (aller + retour) via CreateBookingAction (invariants
 * stock/tarifs/idempotence inchangés) puis les lie dans travel_round_trips.
 * Idempotence : une même `idempotency_key` renvoie le combo existant (aucun
 * doublon de réservations au rejeu).
 *
 * @phpstan-type RoundTripPassengerInput array{
 *     full_name: string,
 *     birth_date?: string|null,
 *     document_type?: string|null,
 *     document_number?: string|null,
 *     age_category: string,
 *     class_id: int,
 *     seat_number?: int|null
 * }
 */
final class CreateRoundTripAction
{
    public function __construct(
        private readonly CreateBookingAction $createBooking,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    /**
     * @param  list<RoundTripPassengerInput>  $passengers
     */
    public function execute(
        TravelTrip $outboundTrip,
        TravelTrip $returnTrip,
        array $passengers,
        BookingSource $source,
        Employee $actor,
        string $idempotencyKey,
    ): TravelRoundTrip {
        $existing = TravelRoundTrip::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof TravelRoundTrip) {
            return $existing->load(['bookingOutbound.passengers', 'bookingReturn.passengers']);
        }

        if ($outboundTrip->company_id !== $returnTrip->company_id) {
            abort(422, 'Les deux trajets doivent appartenir au meme tenant.');
        }

        /** @var TravelRoundTrip $roundTrip */
        $roundTrip = DB::transaction(function () use (
            $outboundTrip,
            $returnTrip,
            $passengers,
            $source,
            $actor,
            $idempotencyKey,
        ): TravelRoundTrip {
            $outbound = $this->createBooking->execute(
                trip: $outboundTrip,
                passengers: $passengers,
                source: $source,
                actor: $actor,
                idempotencyKey: 'rt-out-'.$idempotencyKey,
            );

            $return = $this->createBooking->execute(
                trip: $returnTrip,
                passengers: $passengers,
                source: $source,
                actor: $actor,
                idempotencyKey: 'rt-ret-'.$idempotencyKey,
            );

            /** @var TravelBooking $outbound */
            /** @var TravelBooking $return */

            return TravelRoundTrip::query()->create([
                'booking_outbound_id' => $outbound->id,
                'booking_return_id' => $return->id,
                'idempotency_key' => $idempotencyKey,
                'created_by_user_id' => $actor->id,
            ]);
        });

        $this->outbox->publish($roundTrip->company_id, 'travel.round_trip.created.v1', [
            'round_trip_reference' => $roundTrip->reference,
            'outbound_reference' => $roundTrip->bookingOutbound?->reference,
            'return_reference' => $roundTrip->bookingReturn?->reference,
            'passenger_count' => count($passengers),
        ]);

        return $roundTrip->load(['bookingOutbound.passengers', 'bookingReturn.passengers']);
    }
}

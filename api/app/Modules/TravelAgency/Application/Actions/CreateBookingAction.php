<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-312 (#6042) — Creation d'une reservation guichet (multi-passagers).
 *
 * Invariant stock (spec §4-D4) : la transaction verrouille les sieges
 * choisis avec `SELECT … FOR UPDATE` (jamais de decrement non protege).
 * Si les sieges ne sont pas fournis, ils sont attribues automatiquement
 * parmi les places libres du trajet. Le montant total est calcule depuis
 * les tarifs du trajet (unite mineures, adulte/enfant), jamais accepte du
 * client. Idempotence : une `idempotency_key` deja utilisee renvoie la
 * reservation existante (pas de doublon).
 *
 * @phpstan-type PassengerInput array{
 *     full_name: string,
 *     birth_date?: string|null,
 *     document_type?: string|null,
 *     document_number?: string|null,
 *     age_category: string,
 *     class_id: int,
 *     seat_number?: int|null
 * }
 */
final class CreateBookingAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  list<PassengerInput>  $passengers
     */
    public function execute(
        TravelTrip $trip,
        array $passengers,
        BookingSource $source,
        Employee $actor,
        string $idempotencyKey,
        ?int $customerContactId = null,
    ): TravelBooking {
        $existing = TravelBooking::query()
            ->where('trip_id', $trip->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof TravelBooking) {
            return $existing->load('passengers');
        }

        if ($trip->status->value !== 'published') {
            abort(409, 'Ce trajet n\'est pas ouvert a la reservation.');
        }

        $booking = DB::transaction(function () use ($trip, $passengers, $source, $actor, $idempotencyKey, $customerContactId): TravelBooking {
            // Verrouille le trajet : empeche deux reservations concurrentes
            // de lire le meme inventaire.
            /** @var TravelTrip $lockedTrip */
            $lockedTrip = TravelTrip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Selection des sieges (explicites ou auto-attribues).
            $seats = $this->resolveSeats($lockedTrip, $passengers);

            $total = $this->computeTotal($lockedTrip, $passengers);

            $booking = TravelBooking::query()->create([
                'trip_id' => $lockedTrip->id,
                'status' => BookingStatus::PENDING,
                'passenger_count' => count($passengers),
                'total_amount_minor' => $total,
                'currency' => $this->resolveCurrency($lockedTrip),
                'booking_source' => $source,
                'customer_contact_id' => $customerContactId,
                'booked_by_user_id' => $actor->id,
                'payment_status' => PaymentStatus::PENDING,
                'expires_at' => now()->addMinutes(15),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($passengers as $index => $passengerData) {
                $seat = $seats[$index];

                /** @var TravelPassenger $passenger */
                $passenger = $booking->passengers()->create([
                    'full_name' => $passengerData['full_name'],
                    'birth_date' => $passengerData['birth_date'] ?? null,
                    'document_type' => $passengerData['document_type'] ?? null,
                    'age_category' => AgeCategory::from($passengerData['age_category']),
                    'class_id' => $passengerData['class_id'],
                    'seat_number' => $seat->seat_number,
                    'unit_price_minor' => $this->unitPriceFor($lockedTrip, $passengerData),
                ]);

                if (! empty($passengerData['document_number'])) {
                    $passenger->setDocumentNumber($passengerData['document_number']);
                    $passenger->save();
                }

                // Reserve le siege : statut reserved + rattachement.
                $seat->forceFill([
                    'status' => SeatStatus::RESERVED,
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                    'reserved_until' => now()->addMinutes(15),
                ])->save();
            }

            return $booking;
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.pending.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'passenger_count' => $booking->passenger_count,
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'booking_source' => $source->value,
            'expires_at' => $booking->expires_at?->toIso8601String(),
        ]);

        return $booking->load('passengers');
    }

    /**
     * Verrouille (FOR UPDATE) et attribue les sieges demandes, ou les N
     * premiers libres. Echoue en 409 si un siege demande est indisponible.
     *
     * @param  list<PassengerInput>  $passengers
     * @return list<TravelTripSeat>
     */
    private function resolveSeats(TravelTrip $trip, array $passengers): array
    {
        $explicitSeats = [];
        foreach ($passengers as $passengerData) {
            if (! empty($passengerData['seat_number'])) {
                $explicitSeats[] = $passengerData['seat_number'];
            }
        }

        // Verrouille TOUTES les lignes sieges du trajet : la selection
        // concurrente de sieges libres est serialisee (pas de course).
        /** @var Collection<int, TravelTripSeat> $allSeats */
        $allSeats = TravelTripSeat::query()
            ->where('trip_id', $trip->id)
            ->orderBy('seat_number')
            ->lockForUpdate()
            ->get();

        $available = $allSeats->filter(fn (TravelTripSeat $seat): bool => $seat->status === SeatStatus::FREE);

        if (count($explicitSeats) > 0) {
            $selected = [];
            foreach ($passengers as $passengerData) {
                $seatNumber = $passengerData['seat_number'] ?? null;

                if ($seatNumber === null) {
                    $selected[] = $available->shift();

                    continue;
                }

                $seat = $allSeats->first(fn (TravelTripSeat $s): bool => $s->seat_number === $seatNumber);
                if ($seat === null || $seat->status !== SeatStatus::FREE) {
                    abort(409, 'Siege '.$seatNumber.' indisponible.');
                }

                $selected[] = $seat;
            }

            if (in_array(null, $selected, true)) {
                abort(409, 'Plus assez de places libres sur ce trajet.');
            }

            /** @var list<TravelTripSeat> $selected */
            return $selected;
        }

        $auto = $available->take(count($passengers));
        if ($auto->count() < count($passengers)) {
            abort(409, 'Plus assez de places libres sur ce trajet.');
        }

        return array_values($auto->values()->all());
    }

    /**
     * Devise de la reservation : celle des tarifs du trajet (source de
     * verite du prix), repli sur la devise du tenant.
     */
    private function resolveCurrency(TravelTrip $trip): string
    {
        $price = $trip->prices()->first();

        if ($price instanceof TravelTripPrice) {
            return $price->currency;
        }

        return currentCompany()->currency;
    }

    /**
     * Montant total en unites mineures = somme des tarifs unitaires.
     *
     * @param  list<PassengerInput>  $passengers
     */
    private function computeTotal(TravelTrip $trip, array $passengers): int
    {
        $total = 0;
        foreach ($passengers as $passengerData) {
            $total += $this->unitPriceFor($trip, $passengerData);
        }

        return $total;
    }

    /**
     * Tarif unitaire du passager (adulte/enfant) pour sa classe.
     *
     * @param  PassengerInput  $passengerData
     */
    private function unitPriceFor(TravelTrip $trip, array $passengerData): int
    {
        $price = $trip->prices()
            ->where('class_id', $passengerData['class_id'])
            ->first();

        if (! $price instanceof TravelTripPrice) {
            abort(422, 'Aucun tarif defini pour cette classe sur ce trajet.');
        }

        $isChild = AgeCategory::from($passengerData['age_category']) !== AgeCategory::ADULT;

        return $isChild
            ? ($price->child_price_minor ?? $price->adult_price_minor)
            : $price->adult_price_minor;
    }
}

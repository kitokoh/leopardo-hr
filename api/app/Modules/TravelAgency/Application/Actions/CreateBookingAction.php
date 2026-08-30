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
use Illuminate\Support\Str;

/**
 * TRAVEL-312 (#6042) — Creation d'une reservation guichet (multi-passagers).
 *
 * Invariant stock (spec §4-D4) : la transaction verrouille les sieges
 * choisis avec `SELECT … FOR UPDATE` (jamais de decrement non protege).
 * Si les sieges ne sont pas fournis, ils sont attribues automatiquement
 * (TRAVEL-801/#6092 : regroupement + fenêtre avant). Le montant total est
 * calcule depuis les tarifs du trajet (unite mineures), jamais accepte du
 * client. Idempotence : une `idempotency_key` deja utilisee renvoie la
 * reservation existante (pas de doublon).
 *
 * TRAVEL-802 (#6093) — aller-retour : si `return_trip_id` + `return_passengers`
 * sont fournis, une seconde reservation (leg retour) est creee dans le meme
 * groupe `round_trip_group_id`, liee par `return_booking_id`, avec le tarif
 * combine optionnel (config `travel.pricing.round_trip_discount_percent`).
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
     * @param  list<PassengerInput>|null  $returnPassengers
     */
    public function execute(
        TravelTrip $trip,
        array $passengers,
        BookingSource $source,
        Employee $actor,
        string $idempotencyKey,
        ?int $customerContactId = null,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
        bool $notifyConsent = false,
        ?int $returnTripId = null,
        ?array $returnPassengers = null,
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

        $group = (string) Str::uuid();

        $booking = $this->createSingleBooking(
            trip: $trip,
            passengers: $passengers,
            source: $source,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            customerContactId: $customerContactId,
            contactEmail: $contactEmail,
            contactPhone: $contactPhone,
            notifyConsent: $notifyConsent,
            roundTripGroupId: $group,
            returnBookingId: null,
            isReturnLeg: false,
        );

        // TRAVEL-802 — leg retour (tarif combiné optionnel).
        if ($returnTripId !== null && $returnPassengers !== null && $returnPassengers !== []) {
            /** @var TravelTrip $returnTrip */
            $returnTrip = TravelTrip::query()->findOrFail($returnTripId);

            if ($returnTrip->company_id !== $actor->company_id) {
                abort(404);
            }

            if ($returnTrip->status->value !== 'published') {
                abort(409, 'Le trajet retour n\'est pas ouvert a la reservation.');
            }

            $returnBooking = $this->createSingleBooking(
                trip: $returnTrip,
                passengers: $returnPassengers,
                source: $source,
                actor: $actor,
                idempotencyKey: $idempotencyKey.':return',
                customerContactId: $customerContactId,
                contactEmail: $contactEmail,
                contactPhone: $contactPhone,
                notifyConsent: $notifyConsent,
                roundTripGroupId: $group,
                returnBookingId: $booking->id,
                isReturnLeg: true,
            );

            $booking->forceFill(['return_booking_id' => $returnBooking->id])->save();
        }

        return $booking->load('passengers');
    }

    /**
     * @param  list<PassengerInput>  $passengers
     */
    private function createSingleBooking(
        TravelTrip $trip,
        array $passengers,
        BookingSource $source,
        Employee $actor,
        string $idempotencyKey,
        ?int $customerContactId,
        ?string $contactEmail,
        ?string $contactPhone,
        bool $notifyConsent,
        string $roundTripGroupId,
        ?int $returnBookingId,
        bool $isReturnLeg,
    ): TravelBooking {
        $booking = DB::transaction(function () use (
            $trip,
            $passengers,
            $source,
            $actor,
            $idempotencyKey,
            $customerContactId,
            $contactEmail,
            $contactPhone,
            $notifyConsent,
            $roundTripGroupId,
            $returnBookingId,
            $isReturnLeg,
        ): TravelBooking {
            // Verrouille le trajet : empeche deux reservations concurrentes
            // de lire le meme inventaire.
            /** @var TravelTrip $lockedTrip */
            $lockedTrip = TravelTrip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            $seats = $this->resolveSeats($lockedTrip, $passengers);
            $total = $this->computeTotal($lockedTrip, $passengers);

            // Tarif combiné aller-retour (TRAVEL-802) : remise serveur sur
            // la jambe retour uniquement (config, défaut 0 %).
            if ($isReturnLeg) {
                $discount = (int) config('travel.pricing.round_trip_discount_percent', 0);
                $total = (int) round($total * (100 - max(0, min(100, $discount))) / 100);
            }

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
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'notify_consent' => $notifyConsent,
                'consent_recorded_at' => $notifyConsent ? now() : null,
                'round_trip_group_id' => $roundTripGroupId,
                'return_booking_id' => $returnBookingId,
                'leg' => $isReturnLeg ? 'return' : 'outbound',
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
            'round_trip_group_id' => $booking->round_trip_group_id,
            'leg' => $booking->leg,
        ]);

        return $booking;
    }

    /**
     * Verrouille (FOR UPDATE) et attribue les sieges demandes, ou les N
     * premiers libres. Echoue en 409 si un siege demande est indisponible.
     *
     * @param  list<PassengerInput>  $passengers
     * @return array<int, TravelTripSeat>
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

        $auto = $this->autoAssign($available, count($passengers));
        if (count($auto) < count($passengers)) {
            abort(409, 'Plus assez de places libres sur ce trajet.');
        }

        return $auto;
    }

    /**
     * TRAVEL-801 (#6092) — Assignation automatique des sièges.
     *
     * Algorithme déterministe (fenêtre avant + regroupement) :
     *  1. parcourt les sièges libres dans l'ordre croissant (`seat_number`) ;
     *  2. choisit le PREMIER bloc contigu de taille suffisante pour garder
     *     le groupe ensemble ;
     *  3. en l'absence de bloc contigu, repli sur les premiers libres.
     * Le surclassement manuel reste possible : un `seat_number` explicite
     * dans la requête (agent) est toujours honoré avant cet algorithme.
     *
     * @param  Collection<int, TravelTripSeat>  $available  sièges libres triés
     * @return list<TravelTripSeat>
     */
    private function autoAssign(Collection $available, int $count): array
    {
        /** @var list<TravelTripSeat> $seats */
        $seats = array_values($available->all());

        if (count($seats) < $count) {
            return [];
        }

        $runStart = 0;
        $runLength = 1;

        for ($i = 1; $i < count($seats); $i++) {
            if ($seats[$i]->seat_number === $seats[$i - 1]->seat_number + 1) {
                $runLength++;

                if ($runLength >= $count) {
                    return array_slice($seats, $runStart, $count);
                }

                continue;
            }

            $runStart = $i;
            $runLength = 1;
        }

        // Aucun bloc contigu de taille suffisante : fenêtre avant simple.
        return array_slice($seats, 0, $count);
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

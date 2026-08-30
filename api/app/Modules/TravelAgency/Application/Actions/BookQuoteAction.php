<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\QuoteStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-803 (#6094) — Réservation groupée depuis un devis.
 *
 * Vérifie que le devis est encore valide (non expiré, non déjà réservé) et
 * que le plafond est respecté : le total de la réservation est recalculé
 * serveur et doit correspondre au total figé du devis (409 si les tarifs ont
 * changé entre-temps). La réservation passe par CreateBookingAction
 * (invariants stock/sièges inchangés).
 */
final class BookQuoteAction
{
    public function __construct(
        private readonly CreateBookingAction $createBooking,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    public function execute(TravelQuote $quote, Employee $actor): TravelBooking
    {
        if ($quote->status === QuoteStatus::BOOKED) {
            $booking = $quote->booking;

            if ($booking instanceof TravelBooking) {
                return $booking->load('passengers');
            }
        }

        if ($quote->status !== QuoteStatus::DRAFT) {
            abort(422, 'Ce devis ne peut plus etre resserve.');
        }

        if ($quote->expires_at !== null && $quote->expires_at->isPast()) {
            $quote->forceFill(['status' => QuoteStatus::EXPIRED])->save();

            abort(410, 'Ce devis a expire.');
        }

        /** @var TravelTrip $trip */
        $trip = $quote->trip()->firstOrFail();

        /** @var TravelBooking $booking */
        $booking = DB::transaction(function () use ($quote, $trip, $actor): TravelBooking {
            $passengers = $quote->passengers_json ?? [];

            if (count($passengers) < $quote->passenger_count) {
                abort(422, 'Les passagers du devis sont incomplets.');
            }

            $booking = $this->createBooking->execute(
                trip: $trip,
                passengers: $passengers,
                source: BookingSource::OFFICE,
                actor: $actor,
                idempotencyKey: 'quote-'.$quote->idempotency_key,
                customerContactId: $quote->customer_contact_id,
            );

            if ($booking->total_amount_minor !== $quote->total_amount_minor) {
                abort(409, 'Les tarifs de ce trajet ont change ; le devis doit etre regenere.');
            }

            $quote->forceFill([
                'status' => QuoteStatus::BOOKED,
                'booking_id' => $booking->id,
            ])->save();

            return $booking;
        });

        $this->outbox->publish($quote->company_id, 'travel.quote.booked.v1', [
            'quote_reference' => $quote->reference,
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'passenger_count' => $booking->passenger_count,
        ]);

        return $booking->load('passengers');
    }

}

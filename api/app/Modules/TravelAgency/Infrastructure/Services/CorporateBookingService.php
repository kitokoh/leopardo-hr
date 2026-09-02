<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCorporateAccount;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;

/**
 * TRAVEL-803 (#6094) — Réservations de groupe / corporate.
 *
 * Cycle : devis (prix serveur) → acceptation → réservation groupée avec
 * facturation différée (contrat Accounting). Invariants :
 *   - taille minimale de groupe (config `travel.corporate.min_group_size`) ;
 *   - devis respecté (même trajet, même effectif, montant ≤ devis) ;
 *   - plafond corporate respecté (cumul des réservations ouvertes ≤ crédit).
 */
final class CorporateBookingService
{
    public function __construct(private readonly CreateBookingAction $createBooking) {}

    /**
     * Création d'un devis (prix calculé SERVEUR depuis les tarifs du trajet).
     */
    public function createQuote(
        TravelCorporateAccount $account,
        TravelTrip $trip,
        int $classId,
        int $passengersCount,
        Employee $actor,
    ): TravelQuote {
        $passengersCount = max(1, min(100, $passengersCount));

        /** @var TravelTripPrice|null $price */
        $price = $trip->prices()->where('class_id', $classId)->first();

        if (! $price instanceof TravelTripPrice) {
            abort(422, 'Aucun tarif défini pour cette classe sur ce trajet.');
        }

        $total = (int) $price->adult_price_minor * $passengersCount;

        $validityDays = (int) config('travel.corporate.quote_validity_days', 14);

        /** @var TravelQuote $quote */
        $quote = TravelQuote::query()->create([
            'company_id' => $account->company_id,
            'corporate_account_id' => $account->id,
            'trip_id' => $trip->id,
            'class_id' => $classId,
            'passengers_count' => $passengersCount,
            'total_amount_minor' => $total,
            'currency' => (string) $price->currency,
            'status' => TravelQuote::STATUS_DRAFT,
            'expires_at' => now()->addDays($validityDays),
            'created_by_user_id' => $actor->id,
        ]);

        return $quote;
    }

    public function acceptQuote(TravelQuote $quote, Employee $actor): TravelQuote
    {
        if ($quote->status !== TravelQuote::STATUS_DRAFT) {
            abort(422, 'Seul un devis en brouillon peut être accepté.');
        }

        if ($quote->expires_at !== null && $quote->expires_at->isPast()) {
            $quote->forceFill(['status' => TravelQuote::STATUS_EXPIRED])->save();

            abort(422, 'Ce devis est expiré.');
        }

        $quote->forceFill(['status' => TravelQuote::STATUS_ACCEPTED])->save();

        return $quote->refresh();
    }

    public function cancelQuote(TravelQuote $quote): TravelQuote
    {
        if (in_array($quote->status, [TravelQuote::STATUS_CANCELLED, TravelQuote::STATUS_EXPIRED], true)) {
            return $quote;
        }

        $quote->forceFill(['status' => TravelQuote::STATUS_CANCELLED])->save();

        return $quote->refresh();
    }

    /**
     * Réservation groupée adossée à un compte corporate.
     *
     * @param  list<array<string, mixed>>  $passengers
     */
    public function createGroupBooking(
        TravelTrip $trip,
        array $passengers,
        BookingSource $source,
        Employee $actor,
        string $idempotencyKey,
        TravelCorporateAccount $account,
        ?TravelQuote $quote = null,
    ): TravelBooking {
        if (! $account->is_active) {
            abort(422, 'Compte corporate inactif.');
        }

        $minGroup = (int) config('travel.corporate.min_group_size', 5);

        if (count($passengers) < $minGroup) {
            abort(422, 'Réservation corporate : minimum '.$minGroup.' passagers.');
        }

        // Devis respecté : même trajet, même effectif, montant ≤ devis.
        if ($quote instanceof TravelQuote) {
            if ($quote->corporate_account_id !== $account->id) {
                abort(422, 'Devis incohérent avec le compte corporate.');
            }

            if ($quote->status !== TravelQuote::STATUS_ACCEPTED) {
                abort(422, 'Devis non accepté.');
            }

            if ($quote->trip_id !== $trip->id || $quote->passengers_count !== count($passengers)) {
                abort(422, 'Devis incohérent avec la réservation (trajet ou effectif).');
            }
        }

        $booking = $this->createBooking->execute(
            trip: $trip,
            passengers: $passengers,
            source: $source,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            corporateAccountId: $account->id,
            quoteId: $quote?->id,
            billingDeferred: true,
        );

        // Plafond corporate : cumul des réservations ouvertes ≤ crédit.
        $used = (int) TravelBooking::query()
            ->where('company_id', $account->company_id)
            ->where('corporate_account_id', $account->id)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->whereKeyNot($booking->id)
            ->sum('total_amount_minor');

        if ($used + (int) $booking->total_amount_minor > (int) $account->credit_limit_minor) {
            abort(422, 'Plafond corporate dépassé ('.$account->credit_limit_minor.' '.$account->currency.').');
        }

        return $booking->load('passengers');
    }
}

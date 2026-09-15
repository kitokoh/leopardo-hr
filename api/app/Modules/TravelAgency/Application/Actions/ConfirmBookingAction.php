<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentProvider;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-313 (#6043) — Confirmation d'une reservation (comptant guichet).
 *
 * pending → confirmed : les sieges reserves passent `sold` (plus jamais
 * liberes par l'expiration), le paiement passe `confirmed` (cash),
 * evenement outbox `travel.booking.confirmed.v1` apres commit. Une
 * reservation deja confirmee est idempotente.
 *
 * #7396 — cette action basculait `payment_status` à `confirmed` **sans jamais
 * enregistrer de paiement** : `travel_payments` restait vide pour toute vente
 * comptant au guichet. Conséquences constatées en recette :
 *   - `TravelPdvService::close()` calcule l'attendu à partir des lignes
 *     `travel_payments` (provider `cash`, statut `confirmed`) : l'attendu valait
 *     donc toujours le seul fond de caisse, et **chaque vente comptant
 *     apparaissait comme un écart positif** à la clôture ;
 *   - le rapprochement comptable (settlement) ne voyait pas ces recettes.
 *
 * Le paiement comptant est désormais matérialisé, avec une clé d'idempotence
 * DÉTERMINISTE (`cash:confirm:{reference}`) adossée à l'index unique
 * `(company_id, idempotency_key)` : un rejeu ne peut pas créer de doublon,
 * même en cas d'appel concurrent.
 */
final class ConfirmBookingAction
{
    /** Préfixe de la clé d'idempotence du règlement comptant (#7396). */
    public const CASH_IDEMPOTENCY_PREFIX = 'cash:confirm:';

    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelBooking $booking, Employee $actor): TravelBooking
    {
        if ($booking->status === BookingStatus::CONFIRMED) {
            return $booking;
        }

        if ($booking->status !== BookingStatus::PENDING) {
            abort(422, 'Seule une reservation en attente peut etre confirmee.');
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

            $this->recordCashPayment($booking);
        });

        $this->outbox->publish($booking->company_id, 'travel.booking.confirmed.v1', [
            'booking_reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return $booking->refresh()->load('passengers');
    }

    /**
     * Matérialise l'encaissement comptant (#7396).
     *
     * Le montant est repris de la réservation (jamais fourni par l'appelant),
     * la devise est celle du tenant. `company_id` est posé explicitement : il
     * n'est pas mass-assignable, et l'action peut être appelée hors requête
     * (commande console) où le remplissage automatique du trait ne s'applique
     * pas.
     */
    private function recordCashPayment(TravelBooking $booking): void
    {
        $idempotencyKey = self::CASH_IDEMPOTENCY_PREFIX.$booking->reference;

        $exists = TravelPayment::query()
            ->where('company_id', $booking->company_id)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();

        if ($exists) {
            return;
        }

        $payment = new TravelPayment([
            'booking_id' => $booking->id,
            'provider_code' => PaymentProvider::CASH,
            'amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'status' => PaymentStatus::CONFIRMED,
            'provider_reference' => 'cash:'.$booking->reference,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Non mass-assignable (garde multi-tenant du trait BelongsToCompany).
        $payment->company_id = $booking->company_id;
        $payment->save();
    }
}

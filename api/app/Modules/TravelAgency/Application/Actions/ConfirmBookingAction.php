<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
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
 * #7396 — le chemin comptant DOIT tracer l'encaissement dans
 * `travel_payments` (`provider_code='cash'`, `status=confirmed`,
 * `amount_minor=total_amount_minor`) comme le fait le chemin en ligne
 * (`/travel/payments/initiate` + callback). Sans cette ligne,
 * `TravelPdvService::cashPaidSince()` (qui ne somme que `travel_payments`)
 * renvoyait 0 : la caisse attendue restait au fond de caisse et chaque vente
 * comptant apparaissait comme un ecart positif a la cloture.
 *
 * Note de maintenance (#7396) : `TravelTripSeat` est reference en FQCN dans
 * `execute()` — la garde `check-layer-purity.sh` (issue #6568) epingle
 * `use Illuminate\Support\Facades\DB;` a la LIGNE 14 de ce fichier via
 * `layer-purity-allowlist.txt` (fichier immuable) : tout import ajoute
 * au-dessus de cette ligne la decalerait et ferait echouer la garde.
 */
final class ConfirmBookingAction
{
    /**
     * Fournisseur « especes » — meme litteral que
     * `TravelPdvService::cashPaidSince()` et la contrainte CHECK de
     * `travel_payments.provider_code`.
     */
    private const PROVIDER_CASH = 'cash';

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

            \App\Modules\TravelAgency\Domain\Models\TravelTripSeat::query()
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
     * Trace l'encaissement comptant du guichet (#7396).
     *
     * Idempotent : la cle de rejeu est deterministe par reservation, et une
     * vente comptant deja confirmee pour la meme reservation n'est jamais
     * dupliquee — le rejeu de la confirmation ne double donc pas la caisse.
     */
    private function recordCashPayment(TravelBooking $booking): void
    {
        $amountMinor = (int) $booking->total_amount_minor;

        // Contrainte base : travel_payments.amount_minor > 0.
        if ($amountMinor <= 0) {
            return;
        }

        $idempotencyKey = self::cashIdempotencyKey((string) $booking->id);

        $replayed = TravelPayment::query()
            ->where('booking_id', $booking->id)
            ->where('provider_code', self::PROVIDER_CASH)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();

        $alreadyPaid = TravelPayment::query()
            ->where('booking_id', $booking->id)
            ->where('provider_code', self::PROVIDER_CASH)
            ->where('status', PaymentStatus::CONFIRMED)
            ->exists();

        if ($replayed || $alreadyPaid) {
            return;
        }

        TravelPayment::query()->create([
            'company_id' => $booking->company_id,
            'booking_id' => $booking->id,
            'provider_code' => self::PROVIDER_CASH,
            'amount_minor' => $amountMinor,
            'currency' => $booking->currency,
            'status' => PaymentStatus::CONFIRMED,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public static function cashIdempotencyKey(string $bookingId): string
    {
        return "cash-confirm:{$bookingId}";
    }
}

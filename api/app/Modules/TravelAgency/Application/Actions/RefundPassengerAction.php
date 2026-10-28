<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelRefund;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use App\Modules\TravelAgency\Infrastructure\Services\TravelRefundPolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-808 (#6098) — Remboursement partiel d'un passager.
 *
 * Pénalité calculée serveur (règles d'élasticité, surclassées par les
 * politiques TRAVEL-813) ; montant remboursé = prix unitaire du passager −
 * pénalité. Idempotent par `refund_key` (rejeu sans double remboursement).
 * Si tous les passagers de la réservation sont remboursés, la réservation
 * passe à `refunded` et les billets émis sont révoqués (void).
 */
final class RefundPassengerAction
{
    public function __construct(
        private readonly TravelRefundPolicyResolver $policyResolver,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    public function execute(
        TravelBooking $booking,
        TravelPassenger $passenger,
        Employee $actor,
        string $reason,
        string $refundKey,
    ): TravelRefund {
        if ($booking->company_id !== $passenger->company_id) {
            abort(404);
        }

        if ($passenger->booking_id !== $booking->id) {
            abort(422, 'Ce passager n\'appartient pas a cette reservation.');
        }

        if ($booking->status !== BookingStatus::CONFIRMED) {
            abort(422, 'Seule une reservation confirmee peut etre remboursee.');
        }

        $existing = TravelRefund::query()
            ->where('booking_id', $booking->id)
            ->where('refund_key', $refundKey)
            ->first();

        if ($existing instanceof TravelRefund) {
            return $existing;
        }

        /** @var TravelRefund $refund */
        $refund = DB::transaction(function () use ($booking, $passenger, $actor, $reason, $refundKey): TravelRefund {
            $penaltyPercent = $this->policyResolver->penaltyPercent($booking, (int) $passenger->class_id);
            $gross = (int) $passenger->unit_price_minor;
            $penalty = (int) round($gross * $penaltyPercent / 100);
            $net = max(0, $gross - $penalty);

            $refund = TravelRefund::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'amount_minor' => $net,
                'penalty_minor' => $penalty,
                'currency' => $booking->currency,
                'reason' => $reason,
                'refund_key' => $refundKey,
                'refunded_by_user_id' => $actor->id,
            ]);

            // Billet émis du passager → révoqué (void).
            $booking->tickets()
                ->where('passenger_id', $passenger->id)
                ->where('status', '!=', TicketStatus::VOID->value)
                ->update(['status' => TicketStatus::VOID->value]);

            // Siège libéré (retour au stock libre).
            //
            // #8396 — `booking_id` et `passenger_id` étaient remis à `null` :
            // le lien entre le siège et le passager remboursé disparaissait, donc
            // impossible de répondre à « quel siège ce remboursement a-t-il
            // libéré ? » — la question de base d'un contrôle comptable ou d'une
            // réclamation client. Le siège reste rattaché (traçabilité) et seule
            // `reserved_until` est purgée : le statut FREE le rend de nouveau
            // réservable, ce que vérifie le moteur d'inventaire.
            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('passenger_id', $passenger->id)
                ->update([
                    'status' => SeatStatus::FREE->value,
                    'reserved_until' => null,
                ]);

            // #8396 — le passager n'était jamais marqué remboursé :
            // `refunded_at`/`refunded_amount_minor`/`refund_reason` (colonnes de
            // la migration 6098, exposées par l'API) restaient `null`. Un
            // remboursement exécuté devenait donc invisible sur le dossier
            // passager, et la même personne pouvait être remboursée deux fois
            // par deux chemins différents (le garde-fou d'idempotence ne couvre
            // que `refund_key`).
            $passenger->forceFill([
                'refunded_at' => now(),
                'refunded_amount_minor' => $net,
                'refund_reason' => $reason,
            ])->save();

            // Si tous les passagers sont remboursés → réservation refunded.
            $refundedPassengerIds = TravelRefund::query()
                ->where('booking_id', $booking->id)
                ->whereNotNull('passenger_id')
                ->pluck('passenger_id');

            $allRefunded = $booking->passengers()
                ->whereNotIn('id', $refundedPassengerIds)
                ->count() === 0;

            if ($allRefunded) {
                $booking->forceFill([
                    'status' => BookingStatus::REFUNDED,
                    'payment_status' => PaymentStatus::REFUNDED,
                ])->save();
            }

            return $refund;
        });

        $this->outbox->publish($booking->company_id, 'travel.refund.recorded.v1', [
            'booking_reference' => $booking->reference,
            'refund_key' => $refundKey,
            'passenger_id' => $passenger->id,
            'amount_minor' => $refund->amount_minor,
            'penalty_minor' => $refund->penalty_minor,
            'currency' => $refund->currency,
        ]);

        // #8396 — un remboursement PARTIEL doit être visible par les décideurs
        // de paiement : seul `travel.refund.recorded.v1` (événement interne) était
        // publié, jamais `travel.payment.refunded.v1`. La comptabilité et les
        // notifications ne voyaient donc pas les remboursements partiels, alors
        // qu'ils voient les remboursements totaux. `partial: true` distingue les
        // deux cas côté consommateur (montant ≠ total du dossier).
        $this->outbox->publish($booking->company_id, 'travel.payment.refunded.v1', [
            'booking_reference' => $booking->reference,
            'amount_minor' => $refund->amount_minor,
            'penalty_minor' => $refund->penalty_minor,
            'currency' => $refund->currency,
            'passenger_id' => $passenger->id,
            'partial' => true,
            'refunded_by' => $actor->id,
            'refunded_at' => now()->toIso8601String(),
            'notification_intent' => 'travel.payment.refunded',
            'consent' => false,
        ]);

        return $refund;
    }
}

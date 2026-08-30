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
            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('passenger_id', $passenger->id)
                ->update([
                    'status' => SeatStatus::FREE->value,
                    'booking_id' => null,
                    'passenger_id' => null,
                    'reserved_until' => null,
                ]);

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

        return $refund;
    }
}

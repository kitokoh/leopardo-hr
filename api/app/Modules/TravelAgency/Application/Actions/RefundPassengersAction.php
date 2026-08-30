<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCancellationPolicyService;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-808 (#6098) — Remboursement partiel d'une réservation confirmée.
 *
 * Rembourse un sous-ensemble de passagers : la pénalité est calculée
 * SERVEUR par la politique d'annulation (TRAVEL-813), les sièges des
 * passagers remboursés sont libérés, chaque passager garde sa trace
 * (montant remboursé, date, motif). Idempotence : un passager déjà
 * remboursé est ignoré (rejeu sûr — jamais de double remboursement).
 * Si tous les passagers sont remboursés, la réservation passe `refunded`.
 */
final class RefundPassengersAction
{
    public function __construct(
        private readonly TravelCancellationPolicyService $policies,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    /**
     * @param  list<int>  $passengerIds
     */
    public function execute(TravelBooking $booking, Employee $actor, string $reason, array $passengerIds): TravelBooking
    {
        if ($booking->status === BookingStatus::REFUNDED) {
            return $booking->refresh()->load('passengers');
        }

        if ($booking->status !== BookingStatus::CONFIRMED) {
            abort(422, 'Seule une réservation confirmée peut être remboursée.');
        }

        $booking->load('passengers', 'trip');
        $selected = $this->selectPassengers($booking, $passengerIds);

        if ($selected === []) {
            abort(422, 'Aucun passager éligible au remboursement.');
        }

        $departureAt = $this->departureAt($booking);

        $refundedTotal = 0;
        $penaltyTotal = 0;

        $refundedAll = DB::transaction(function () use ($booking, $selected, $reason, &$refundedTotal, &$penaltyTotal, $departureAt): bool {
            $allRefunded = true;

            foreach ($booking->passengers as $passenger) {
                if (! in_array($passenger->id, array_map('intval', $selected), true)) {
                    $allRefunded = false;

                    continue;
                }

                if ($passenger->refunded_at !== null) {
                    continue; // Déjà remboursé (rejeu) : jamais deux fois.
                }

                $breakdown = $this->policies->refundBreakdownForPassenger($passenger, $departureAt);

                if (! $breakdown['refundable']) {
                    abort(422, 'Passager non remboursable selon la politique d\'annulation ('.$passenger->full_name.').');
                }

                $refundedTotal += $breakdown['refund_amount_minor'];
                $penaltyTotal += $breakdown['penalty_minor'];

                $passenger->forceFill([
                    'refunded_at' => now(),
                    'refunded_amount_minor' => $breakdown['refund_amount_minor'],
                    'refund_reason' => $reason,
                ])->save();

                TravelTripSeat::query()
                    ->where('trip_id', $booking->trip_id)
                    ->where('passenger_id', $passenger->id)
                    ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);
            }

            return $allRefunded;
        });

        if ($refundedTotal === 0) {
            return $booking->refresh()->load('passengers'); // Tout était déjà remboursé.
        }

        if ($refundedAll) {
            DB::transaction(function () use ($booking): void {
                $booking->forceFill([
                    'status' => BookingStatus::REFUNDED,
                    'payment_status' => PaymentStatus::REFUNDED,
                    'version' => $booking->version + 1,
                ])->save();

                TravelTripSeat::query()
                    ->where('trip_id', $booking->trip_id)
                    ->where('booking_id', $booking->id)
                    ->where('status', SeatStatus::SOLD)
                    ->update(['status' => SeatStatus::FREE, 'reserved_until' => null]);

                // Trace du paiement intégralement remboursé (audit).
                TravelPayment::query()
                    ->where('booking_id', $booking->id)
                    ->where('status', PaymentStatus::CONFIRMED)
                    ->update(['status' => PaymentStatus::REFUNDED]);
            });

            $this->outbox->publish($booking->company_id, 'travel.booking.cancelled.v1', [
                'booking_reference' => $booking->reference,
                'trip_id' => $booking->trip_id,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now()->toIso8601String(),
                'reason' => $reason,
            ]);
        }

        $this->outbox->publish($booking->company_id, 'travel.payment.refunded.v1', [
            'booking_reference' => $booking->reference,
            'amount_minor' => $refundedTotal,
            'penalty_minor' => $penaltyTotal,
            'currency' => $booking->currency,
            'passenger_ids' => array_map('intval', $selected),
            'partial' => ! $refundedAll,
            'refunded_by' => $actor->id,
            'refunded_at' => now()->toIso8601String(),
        ]);

        return $booking->refresh()->load('passengers');
    }

    /**
     * @param  list<int>  $passengerIds
     * @return list<int>
     */
    private function selectPassengers(TravelBooking $booking, array $passengerIds): array
    {
        $ids = array_map('intval', $passengerIds);

        $bookingIds = $booking->passengers
            ->filter(fn (TravelPassenger $p): bool => in_array($p->id, $ids, true))
            ->map(fn (TravelPassenger $p): int => $p->id)
            ->values()
            ->all();

        // 404 sûr : un id étranger à la réservation est rejeté sans révéler
        // l'existence d'un passager d'un autre tenant.
        if (count($bookingIds) !== count(array_unique($ids))) {
            abort(404, 'Passager inconnu pour cette réservation.');
        }

        /** @var list<int> $bookingIds */
        return $bookingIds;
    }

    private function departureAt(TravelBooking $booking): Carbon
    {
        $departure = $booking->trip?->departure_date?->copy()
            ->setTimeFromTimeString((string) ($booking->trip?->departure_time ?? '00:00'));

        return $departure instanceof Carbon ? $departure : now()->addDay();
    }
}

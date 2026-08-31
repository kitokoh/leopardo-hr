<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations restées `pending`.
 *
 * Passe bornée, tenant-scoped : les réservations `pending` dont `expires_at`
 * est dépassé passent à `cancelled`, leurs sièges sont libérés (`free`,
 * `reserved_until` remis à null) et un événement outbox
 * `travel.booking.expired.v1` est publié APRÈS commit (pattern
 * `CancelBookingAction`, TRAVEL-314). Idempotence : seules les réservations
 * encore `pending` sont traitées — un rejeu (retry worker, double run) ne
 * touche ni les réservations déjà annulées ni les sièges déjà libérés, et la
 * clé d'idempotence de l'événement (booking-expired-{id}) déduplique la
 * publication. Log borné (compte par compagnie, aucune PII).
 */
final class ExpirePendingBookingsJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** Taille maximale d'un lot par passe — log borné, pas de surcharge. */
    private const BATCH_LIMIT = 200;

    public function __construct(public readonly string $companyId) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(TravelOutboxPublisher $outbox): void
    {
        $due = TravelBooking::query()
            ->where('status', BookingStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(self::BATCH_LIMIT)
            ->get();

        if ($due->isEmpty()) {
            return;
        }

        $expired = 0;

        foreach ($due as $booking) {
            if ($this->expire($booking, $outbox)) {
                $expired++;
            }
        }

        // Log borné : compte par compagnie uniquement — aucune PII, aucun
        // détail par réservation (règle « secrets et PII hors logs »).
        Log::info('travel.bookings.expired', [
            'company_id' => $this->companyId,
            'count' => $expired,
            'batch_limit' => self::BATCH_LIMIT,
        ]);
    }

    private function expire(TravelBooking $booking, TravelOutboxPublisher $outbox): bool
    {
        // Idempotence : déjà annulée / confirmée → on ne touche à rien.
        if ($booking->status !== BookingStatus::PENDING) {
            return false;
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill([
                'status' => BookingStatus::CANCELLED,
                'expires_at' => null,
                'version' => $booking->version + 1,
            ])->save();

            TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->update([
                    'status' => SeatStatus::FREE->value,
                    'reserved_until' => null,
                ]);
        });

        $this->publishExpiredEvent($booking, $outbox);

        return true;
    }

    private function publishExpiredEvent(TravelBooking $booking, TravelOutboxPublisher $outbox): void
    {
        $outbox->publish(
            $booking->company_id,
            'travel.booking.expired.v1',
            [
                'booking_reference' => $booking->reference,
                'trip_id' => $booking->trip_id,
                'reason' => 'pending_expired',
                'expired_at' => now()->toIso8601String(),
            ],
            idempotencyKey: 'booking-expired-'.$booking->id,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantReservationConflictException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * RESTO-601 (#6206) / RESTO-602 (#6207) / RESTO-603 (#6208) — Réservations.
 *
 * Service métier des réservations : détection de conflit de créneau (409),
 * transitions validées (confirm → outbox `restaurant.reservation.confirmed.v1`,
 * check-in → seated, no-show, cancel avec politique d'annulation), et
 * recherche de disponibilité par table/créneau/couverts.
 */
final class RestaurantReservationService
{
    /** Durée d'un créneau de table (fenêtre anti-chevauchement), en minutes. */
    public const SLOT_WINDOW_MINUTES = 120;

    public function __construct(
        private readonly RestaurantCancellationPolicyService $cancellationPolicy,
    ) {
    }

    /** Statuts qui occupent un créneau (bloquent une table). */
    private const OCCUPYING_STATUSES = ['pending', 'confirmed', 'seated'];

    /**
     * Vérifie qu'aucune réservation active ne chevauche le créneau demandé
     * sur la même table — lève `RestaurantReservationConflictException` (409).
     */
    public function assertNoConflict(
        string $companyId,
        int $branchId,
        int $tableId,
        Carbon $reservedAt,
        ?int $ignoreReservationId = null,
    ): void {
        $conflict = $this->overlappingReservationQuery($companyId, $branchId, $tableId, $reservedAt)
            ->when($ignoreReservationId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreReservationId))
            ->exists();

        if ($conflict) {
            throw new RestaurantReservationConflictException(
                'La table est déjà réservée sur ce créneau (fenêtre de '.self::SLOT_WINDOW_MINUTES.' minutes).',
            );
        }
    }

    /**
     * Confirme une réservation en attente + événement outbox.
     */
    public function confirm(RestaurantReservation $reservation, Employee $actor): RestaurantReservation
    {
        if ($reservation->status !== ReservationStatus::PENDING) {
            throw new RuntimeException('Seule une réservation en attente peut être confirmée.');
        }

        $reservation->status = ReservationStatus::CONFIRMED;
        $reservation->save();

        RestaurantOutboxEvent::query()->firstOrCreate(
            [
                'company_id' => $reservation->company_id,
                'idempotency_key' => 'reservation-confirmed:'.$reservation->id,
            ],
            [
                'event_type' => 'restaurant.reservation.confirmed.v1',
                'payload_redacted' => [
                    'reservation_id' => $reservation->id,
                    'branch_id' => $reservation->branch_id,
                    'table_id' => $reservation->table_id,
                    'covers' => $reservation->covers,
                    'reserved_at' => $reservation->reserved_at->toIso8601String(),
                ],
                'status' => RestaurantOutboxEvent::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        return $reservation;
    }

    /**
     * Check-in : la réservation confirmée passe `seated` (table affectée).
     */
    public function checkIn(RestaurantReservation $reservation, ?int $tableId = null): RestaurantReservation
    {
        if ($reservation->status !== ReservationStatus::CONFIRMED) {
            throw new RuntimeException('Seule une réservation confirmée peut être enregistrée à l\'arrivée.');
        }

        if ($tableId !== null && $tableId !== $reservation->table_id) {
            $this->assertNoConflict(
                $reservation->company_id,
                $reservation->branch_id,
                $tableId,
                $reservation->reserved_at,
                $reservation->id,
            );
            $reservation->table_id = $tableId;
        }

        $reservation->status = ReservationStatus::SEATED;
        $reservation->save();

        return $reservation;
    }

    /**
     * No-show : réservation non honorée.
     */
    public function noShow(RestaurantReservation $reservation): RestaurantReservation
    {
        if (! in_array($reservation->status->value, [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value], true)) {
            throw new RuntimeException('Seule une réservation en attente ou confirmée peut passer no-show.');
        }

        $reservation->status = ReservationStatus::NO_SHOW;
        $reservation->save();

        return $reservation;
    }

    /**
     * Annulation avec politique d'annulation (RESTO-603) : pénalité serveur,
     * montant remboursable calculé à partir du dépôt.
     *
     * @return array{reservation: RestaurantReservation, penalty_minor: int, refundable_minor: int}
     */
    public function cancel(RestaurantReservation $reservation, ?string $reason = null): array
    {
        if (in_array($reservation->status->value, [ReservationStatus::COMPLETED->value, ReservationStatus::NO_SHOW->value, ReservationStatus::CANCELLED->value], true)) {
            throw new RuntimeException('Cette réservation ne peut plus être annulée.');
        }

        $policy = $this->cancellationPolicy->evaluate($reservation);

        $reservation->status = ReservationStatus::CANCELLED;
        $reservation->notes_redacted = $reason !== null
            ? trim(($reservation->notes_redacted ?? '').' | Annulation : '.$reason)
            : $reservation->notes_redacted;
        $reservation->save();

        return [
            'reservation' => $reservation,
            'penalty_minor' => $policy['penalty_minor'],
            'refundable_minor' => $policy['refundable_minor'],
        ];
    }

    /**
     * RESTO-602 — Tables libres pour un créneau/couverts donnés.
     *
     * @return array<int, array{table_id: int, label: string, capacity: int, zone_id: int|null}>
     */
    public function availableTables(string $companyId, int $branchId, int $covers, Carbon $start, Carbon $end): array
    {
        $tables = \App\Modules\RestaurantManager\Domain\Models\RestaurantTable::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('capacity', '>=', $covers)
            ->orderBy('label')
            ->get();

        $available = [];

        foreach ($tables as $table) {
            $busy = RestaurantReservation::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('table_id', $table->id)
                ->whereIn('status', self::OCCUPYING_STATUSES)
                ->where(function (Builder $q) use ($start, $end): void {
                    $q->whereBetween('reserved_at', [$start->copy()->subMinutes(self::SLOT_WINDOW_MINUTES), $end->copy()->addMinutes(self::SLOT_WINDOW_MINUTES)]);
                })
                ->exists();

            if (! $busy) {
                $available[] = [
                    'table_id' => (int) $table->id,
                    'label' => (string) $table->label,
                    'capacity' => (int) $table->capacity,
                    'zone_id' => $table->zone_id,
                ];
            }
        }

        return $available;
    }

    /**
     * @return Builder<RestaurantReservation>
     */
    private function overlappingReservationQuery(string $companyId, int $branchId, int $tableId, Carbon $reservedAt): Builder
    {
        $windowStart = $reservedAt->copy()->subMinutes(self::SLOT_WINDOW_MINUTES);
        $windowEnd = $reservedAt->copy()->addMinutes(self::SLOT_WINDOW_MINUTES);

        return RestaurantReservation::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('table_id', $tableId)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->whereBetween('reserved_at', [$windowStart, $windowEnd]);
    }
}

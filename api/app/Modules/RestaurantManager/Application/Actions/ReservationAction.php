<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use RuntimeException;

/**
 * RESTO-601 (#6206) — Transitions d'une réservation.
 *
 * Workflow : pending → confirmed → seated → completed ; annulation et
 * no-show depuis pending|confirmed. Événement
 * `restaurant.reservation.confirmed.v1` (consommateurs Notifications/CRM,
 * spec §6.3) publié à la confirmation — payload redigé (aucune PII).
 */
final class ReservationAction
{
    public const EVENT_RESERVATION_CONFIRMED = 'restaurant.reservation.confirmed.v1';

    public function __construct(private readonly RestaurantOutboxPublisher $outbox)
    {
    }

    public function confirm(Employee $actor, RestaurantReservation $reservation): RestaurantReservation
    {
        return $this->transition($actor, $reservation, ReservationStatus::CONFIRMED);
    }

    public function checkIn(Employee $actor, RestaurantReservation $reservation): RestaurantReservation
    {
        return $this->transition($actor, $reservation, ReservationStatus::SEATED);
    }

    public function noShow(Employee $actor, RestaurantReservation $reservation): RestaurantReservation
    {
        return $this->transition($actor, $reservation, ReservationStatus::NO_SHOW);
    }

    public function cancel(Employee $actor, RestaurantReservation $reservation): RestaurantReservation
    {
        return $this->transition($actor, $reservation, ReservationStatus::CANCELLED);
    }

    public function complete(Employee $actor, RestaurantReservation $reservation): RestaurantReservation
    {
        return $this->transition($actor, $reservation, ReservationStatus::COMPLETED);
    }

    private function transition(Employee $actor, RestaurantReservation $reservation, ReservationStatus $target): RestaurantReservation
    {
        if ($reservation->company_id !== $actor->company_id) {
            throw new RuntimeException('Reservation does not belong to tenant.');
        }

        $allowed = match ($target) {
            ReservationStatus::CONFIRMED => [ReservationStatus::PENDING],
            ReservationStatus::SEATED => [ReservationStatus::CONFIRMED],
            ReservationStatus::COMPLETED => [ReservationStatus::SEATED],
            ReservationStatus::NO_SHOW => [ReservationStatus::PENDING, ReservationStatus::CONFIRMED],
            ReservationStatus::CANCELLED => [ReservationStatus::PENDING, ReservationStatus::CONFIRMED],
            default => [],
        };

        if (! in_array($reservation->status, $allowed, true)) {
            abort(409, sprintf('Transition not allowed from "%s" to "%s".', $reservation->status->value, $target->value));
        }

        $reservation->forceFill(['status' => $target->value])->save();

        if ($target === ReservationStatus::CONFIRMED) {
            $this->outbox->publish(
                $reservation->company_id,
                self::EVENT_RESERVATION_CONFIRMED,
                [
                    'reservation_id' => $reservation->id,
                    'reference' => $reservation->reference,
                    'branch_id' => $reservation->branch_id,
                    'table_id' => $reservation->table_id,
                    'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                    'covers' => $reservation->covers,
                    'status' => $reservation->status->value,
                ],
            );
        }

        $reservation->refresh();

        return $reservation;
    }
}

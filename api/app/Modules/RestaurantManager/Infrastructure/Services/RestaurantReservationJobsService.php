<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;

/**
 * RESTO-608 (#6213) — Jobs de réservations : no-show et rappels J-1.
 *
 * - `markNoShows` : réservations confirmées/en attente dont l'heure est
 *   dépassée (grace 2h) → `no_show` (unique par construction : le statut
 *   `no_show` exclut la réservation des prochains passages — idempotent).
 * - `sendReminders` : rappel pour les réservations des prochaines 24 h,
 *   événement outbox `restaurant.reservation.reminder.v1` dédupliqué par
 *   (réservation, jour) — pas de double rappel (critère d'acceptation).
 */
final class RestaurantReservationJobsService
{
    /** Délai de grâce après l'heure réservée avant le passage no-show (heures). */
    public const NO_SHOW_GRACE_HOURS = 2;

    /** Fenêtre de rappel : réservations dans les prochaines 24 heures. */
    public const REMINDER_WINDOW_HOURS = 24;

    /**
     * @return array{no_shows: int, reminders_created: int, reminders_duplicates: int}
     */
    public function run(Company $company, ?int $branchId = null): array
    {
        $noShows = $this->markNoShows($company->id, $branchId);
        $reminders = $this->sendReminders($company->id, $branchId);

        return [
            'no_shows' => $noShows,
            'reminders_created' => $reminders['created'],
            'reminders_duplicates' => $reminders['duplicates'],
        ];
    }

    public function markNoShows(string $companyId, ?int $branchId = null): int
    {
        $cutoff = now()->subHours(self::NO_SHOW_GRACE_HOURS);

        $query = RestaurantReservation::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
            ->where('reserved_at', '<', $cutoff);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $count = 0;

        foreach ($query->get() as $reservation) {
            // Le filtre de statut rend le passage no-show unique (idempotent).
            $reservation->status = ReservationStatus::NO_SHOW;
            $reservation->save();
            $count++;
        }

        return $count;
    }

    /**
     * @return array{created: int, duplicates: int}
     */
    public function sendReminders(string $companyId, ?int $branchId = null): array
    {
        $start = now()->addHour();
        $end = now()->addHours(self::REMINDER_WINDOW_HOURS);

        $query = RestaurantReservation::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
            ->whereBetween('reserved_at', [$start, $end]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $day = now()->toDateString();
        $created = 0;
        $duplicates = 0;

        foreach ($query->get() as $reservation) {
            $idempotencyKey = sprintf('reservation-reminder:%d:%s', $reservation->id, $day);

            $event = RestaurantOutboxEvent::query()->firstOrCreate(
                ['company_id' => $companyId, 'idempotency_key' => $idempotencyKey],
                [
                    'event_type' => 'restaurant.reservation.reminder.v1',
                    'payload_redacted' => [
                        'reservation_id' => $reservation->id,
                        'branch_id' => $reservation->branch_id,
                        'contact_phone' => $reservation->contact_phone,
                        'reserved_at' => $reservation->reserved_at->toIso8601String(),
                    ],
                    'status' => RestaurantOutboxEvent::STATUS_PENDING,
                    'attempts' => 0,
                    'available_at' => now(),
                ],
            );

            $event->wasRecentlyCreated ? $created++ : $duplicates++;
        }

        return ['created' => $created, 'duplicates' => $duplicates];
    }
}

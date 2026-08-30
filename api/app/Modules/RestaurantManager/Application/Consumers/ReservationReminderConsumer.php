<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\RestaurantManager\Console\Commands\RestaurantSendRemindersCommand;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;

/**
 * RESTO-608 (#6213) — Consommateur `restaurant.reservation.reminder.v1`.
 *
 * Notifie l'équipe de la branche (principal/rh/manager) qu'une réservation
 * confirmée arrive sous 24 h (préparation de la table et du service).
 * Passe par CommunicationService (BC-13) : préférences, heures calmes,
 * quotas et journal d'audit respectés. Le payload outbox est redigé (aucune
 * PII client) ; les détails sont rechargés dans le contexte tenant.
 */
final class ReservationReminderConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === RestaurantSendRemindersCommand::EVENT_RESERVATION_REMINDER;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $reservationId = (int) ($payload['reservation_id'] ?? 0);

        if ($reservationId <= 0) {
            return;
        }

        $reservation = RestaurantReservation::query()->find($reservationId);

        if (! $reservation instanceof RestaurantReservation) {
            return;
        }

        $staff = Employee::query()
            ->where('company_id', $reservation->company_id)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'manager'])
            ->get();

        foreach ($staff as $employee) {
            $this->communication->notifyEmployee($employee, 'restaurant_reservation_reminder', [
                'title' => 'Réservation à venir (J-1)',
                'body' => sprintf(
                    'Réservation %s : %d couvert(s) le %s — préparez la table.',
                    $reservation->reference,
                    $reservation->covers,
                    $reservation->reserved_at?->format('d/m/Y H:i'),
                ),
                'category' => 'restaurant',
            ], ['app', 'push']);
        }
    }
}

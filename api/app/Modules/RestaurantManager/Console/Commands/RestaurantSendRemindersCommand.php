<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * restaurant:send-reminders — Rappel J-1 des réservations confirmées
 * (RESTO-608/#6213, spec §4.3).
 *
 * Pour chaque réservation `confirmed` dont l'arrivée est prévue dans la
 * fenêtre des prochaines 24 h et qui n'a pas encore été rappelée
 * (`reminder_sent_at` null) : marque `reminder_sent_at` puis publie
 * l'événement `restaurant.reservation.reminder.v1` (payload redigé, sans
 * PII) — le consommateur d'outbox notifie l'équipe de la branche via
 * CommunicationService (BC-13).
 *
 * Idempotence : « pas de double rappel » — le flag + l'événement outbox
 * (clé dérivée) dédupliquent le rejeu.
 *
 * Usage : php artisan restaurant:send-reminders
 * Scheduler : toutes les heures.
 */
class RestaurantSendRemindersCommand extends Command
{
    public const EVENT_RESERVATION_REMINDER = 'restaurant.reservation.reminder.v1';

    protected $signature = 'restaurant:send-reminders {--window-hours=24 : fenêtre de rappel avant reserved_at}';

    protected $description = 'Publie le rappel J-1 des réservations confirmées (idempotent, sans doublon).';

    public function __construct(
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $windowHours = max(1, (int) $this->option('window-hours'));
        $from = now();
        $to = now()->addHours($windowHours);

        $reservations = RestaurantReservation::query()
            ->where('status', ReservationStatus::CONFIRMED->value)
            ->whereBetween('reserved_at', [$from, $to])
            ->whereNull('reminder_sent_at')
            ->get();

        $notified = 0;

        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation): void {
                $reservation->forceFill(['reminder_sent_at' => now()])->save();

                $this->outbox->publish(
                    $reservation->company_id,
                    self::EVENT_RESERVATION_REMINDER,
                    [
                        'reservation_id' => $reservation->id,
                        'branch_id' => $reservation->branch_id,
                        'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                        'covers' => $reservation->covers,
                    ],
                );
            });

            $notified++;
        }

        $this->info("[restaurant:send-reminders] {$notified} rappel(s) publié(s).");

        return self::SUCCESS;
    }
}

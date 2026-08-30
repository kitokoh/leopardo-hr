<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * restaurant:no-show-expire — Expire les réservations non honorées
 * (RESTO-608/#6213, spec §4.3).
 *
 * Toute réservation `confirmed` dont l'heure d'arrivée est dépassée de plus
 * que le délai de grâce passe à `no_show` : le créneau est libéré (la
 * réservation n'occupe plus la table pour les disponibilités à venir).
 *
 * Idempotence : seules les réservations encore `confirmed` sont traitées —
 * un rejeu du job ne peut pas marquer deux fois la même réservation
 * (« no-show unique », critère d'acceptation RESTO-608).
 *
 * Usage : php artisan restaurant:no-show-expire --grace-minutes=60
 * Scheduler : toutes les 15 minutes.
 */
class RestaurantNoShowExpireCommand extends Command
{
    protected $signature = 'restaurant:no-show-expire
        {--grace-minutes=60 : délai de grâce après reserved_at avant no-show}';

    protected $description = 'Marque no_show les réservations confirmées non honorées (idempotent).';

    public function handle(): int
    {
        $graceMinutes = max(0, (int) $this->option('grace-minutes'));
        $cutoff = now()->subMinutes($graceMinutes);

        $updated = DB::table('restaurant_reservations')
            ->where('status', ReservationStatus::CONFIRMED->value)
            ->where('reserved_at', '<', $cutoff)
            ->update(['status' => ReservationStatus::NO_SHOW->value, 'updated_at' => now()]);

        $this->info("[restaurant:no-show-expire] {$updated} réservation(s) passée(s) en no_show.");

        return self::SUCCESS;
    }
}

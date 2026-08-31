<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\TravelAgency\Application\Actions\ExpireBookingsAction;
use Illuminate\Console\Command;

/**
 * travel:expire-bookings — Expire les réservations pending dont le délai
 * est dépassé (TRAVEL-418, issue #6070).
 *
 * Libère les sièges réservés et publie `travel.booking.expired.v1`.
 * Idempotent : les réservations déjà traitées ne sont jamais re-touchées.
 *
 * Usage : php artisan travel:expire-bookings --limit=100
 */
class TravelExpireBookingsCommand extends Command
{
    protected $signature = 'travel:expire-bookings
        {--limit=100 : nombre max de réservations par passe (défaut 100)}';

    protected $description = 'Expire les réservations TravelAgency pending dont expires_at est dépassée (libère les sièges).';

    public function handle(ExpireBookingsAction $action): int
    {
        $count = $action->execute((int) $this->option('limit'));

        $this->info("Réservations TravelAgency expirées : {$count}.");

        return self::SUCCESS;
    }
}

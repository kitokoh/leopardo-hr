<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * travel:expire-adverts — Expiration et archivage des annonces payantes
 * (TRAVEL-908, #6111).
 *
 *  - `published → expired` dès que `expires_at ≤ now` (l'annonce n'est plus
 *    visible) ;
 *  - `expired → archived` après ARCHIVE_AFTER_DAYS (nettoyage du cycle de
 *    vie).
 *
 * Idempotent (filtres d'état explicites) ; planifié quotidiennement.
 */
class TravelExpireAdvertsCommand extends Command
{
    protected $signature = 'travel:expire-adverts';

    protected $description = 'Fait expirer les annonces payantes publiées et archive les expirées anciennes (idempotent).';

    /** Archivage d'une annonce expirée après N jours. */
    private const ARCHIVE_AFTER_DAYS = 90;

    public function handle(): int
    {
        // Expiration des annonces publiées dont la validité est dépassée.
        $expired = DB::table('travel_adverts')
            ->where('status', TravelAdvert::STATUS_PUBLISHED)
            ->where('expires_at', '<=', now())
            ->update(['status' => TravelAdvert::STATUS_EXPIRED, 'updated_at' => now()]);

        // Archivage des annonces expirées depuis longtemps.
        $archived = DB::table('travel_adverts')
            ->where('status', TravelAdvert::STATUS_EXPIRED)
            ->where('expires_at', '<', now()->subDays(self::ARCHIVE_AFTER_DAYS))
            ->update(['status' => TravelAdvert::STATUS_ARCHIVED, 'updated_at' => now()]);

        $this->info("[travel:expire-adverts] {$expired} expiree(s), {$archived} archivee(s).");

        return self::SUCCESS;
    }
}

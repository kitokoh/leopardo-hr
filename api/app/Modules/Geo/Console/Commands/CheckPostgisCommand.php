<?php

declare(strict_types=1);

namespace App\Modules\Geo\Console\Commands;

use App\Modules\Geo\Infrastructure\Services\GeoCapabilities;
use Illuminate\Console\Command;

/**
 * GEO-01 (#8350, BC-33 GEO) — diagnostic PostGIS.
 *
 * `php artisan geo:check-postgis` : affiche la disponibilité et la version de
 * l'extension PostGIS. Toujours en sortie SUCCESS : l'absence de PostGIS est
 * un mode dégradé SUPPORTÉ (fallback Haversine, GEO-03), pas une panne — le
 * runbook docs/infra/POSTGIS_NEON.md décrit le provisionnement Neon.
 */
final class CheckPostgisCommand extends Command
{
    protected $signature = 'geo:check-postgis';

    public function __construct()
    {
        parent::__construct();

        // PA2-I18N-007 : description via le catalogue (jamais d'accentué en dur).
        $this->description = (string) __('geo.check_postgis_description');
    }

    public function handle(GeoCapabilities $capabilities): int
    {
        $capabilities->forget();

        if (! $capabilities->postgisAvailable()) {
            $this->warn((string) __('geo.postgis_unavailable'));

            return self::SUCCESS;
        }

        $this->info((string) __('geo.postgis_available', ['version' => $capabilities->postgisVersion() ?? 'unknown']));

        return self::SUCCESS;
    }
}

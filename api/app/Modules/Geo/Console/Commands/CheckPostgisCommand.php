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

    protected $description = 'Vérifie la disponibilité de l\'extension PostGIS (BC-33 GEO)';

    public function handle(GeoCapabilities $capabilities): int
    {
        $capabilities->forget();

        if (! $capabilities->postgisAvailable()) {
            $this->warn('PostGIS indisponible : le module geo fonctionne en mode dégradé (Haversine). Voir docs/infra/POSTGIS_NEON.md.');

            return self::SUCCESS;
        }

        $this->info(sprintf('PostGIS disponible (version %s).', $capabilities->postgisVersion() ?? 'inconnue'));

        return self::SUCCESS;
    }
}

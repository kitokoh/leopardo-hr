<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GEO-01 (#8350, épic #8349, BC-34 GEO) — activation de l'extension PostGIS.
 *
 * Première introduction de PostGIS dans le dépôt : jusqu'ici, la géo était
 * assurée par des colonnes décimales + Haversine (geofencing Attendance,
 * annuaire public Restaurant). Le core géospatial transverse `geo` (spec
 * docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md) s'appuie sur
 * `geography(Point, 4326)`, les index GIST et les fonctions ST_*.
 *
 * Base de production : Neon Postgres — PostGIS est supporté nativement et
 * activable par le rôle applicatif (pas de superuser requis).
 *
 * Ré-exécutable : CREATE EXTENSION IF NOT EXISTS. WITH SCHEMA public : le
 * search_path applicatif est « shared_tenants,public » (DB_SEARCH_PATH) —
 * sans schéma explicite, l'extension serait créée dans shared_tenants alors
 * que les fonctions ST_* sont résolues via public.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // #9670 : PostGIS est un prérequis d'infrastructure, pas une dépendance
        // Composer. Sur une base `postgres:16` nue, `CREATE EXTENSION postgis`
        // échoue avec un « could not open extension control file » opaque qui
        // casse l'onboarding documenté. On vérifie donc la disponibilité de
        // l'extension et on échoue avec un message actionnable.
        if (! $this->postgisAvailable()) {
            throw new RuntimeException(
                'PostGIS est requis mais indisponible sur cette base PostgreSQL. '
                .'Installez l\'extension (Debian/Ubuntu : `apt install postgresql-15-postgis-3`) '
                .'ou utilisez l\'image Docker `postgis/postgis:16-3.5-alpine`. '
                .'Voir DEVELOPMENT.md § Prérequis.'
            );
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public');
    }

    /**
     * L'extension est-elle installable sur ce serveur ? `pg_available_extensions`
     * liste les extensions dont le fichier de contrôle est présent sur le disque
     * — c'est exactement ce qui manque sur une image PostgreSQL nue.
     */
    private function postgisAvailable(): bool
    {
        $row = DB::selectOne(
            "SELECT 1 FROM pg_available_extensions WHERE name = 'postgis'"
        );

        return $row !== null;
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};

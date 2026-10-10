<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GEO-01 (#8350, épic #8349, BC-33 GEO) — activation de l'extension PostGIS.
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

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};

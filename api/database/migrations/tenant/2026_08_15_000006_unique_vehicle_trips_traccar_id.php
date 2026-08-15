<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #3369 — garde-fou DB contre les doublons de trajets Traccar :
 * index unique PARTIEL sur `vehicle_trips (company_id, traccar_trip_id)`
 * pour les lignes avec traccar_trip_id non nul.
 *
 * Le contrôleur déduplique via exists() — sans contrainte, deux requêtes
 * concurrentes (même fenêtre) créaient des doublons. Cet index est la
 * ceinture de sécurité au niveau données (comme #2669 pour attendance).
 *
 * Données héritées : on conserve le trajet le PLUS ANCIEN (MIN(id)) par
 * traccar_trip_id et on supprime les doublons avant de créer l'index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = (string) (DB::selectOne('SELECT current_schema() AS schema')?->schema ?? 'public');

        if ($schema === '') {
            return;
        }

        $indexName = 'vehicle_trips_traccar_trip_id_unique_per_company';

        $exists = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
            [$schema, $indexName]
        );

        if ($exists !== null) {
            return;
        }

        // Déduplication des données héritées : garder MIN(id) par
        // traccar_trip_id non nul, supprimer le reste.
        DB::statement(
            "DELETE FROM {$schema}.vehicle_trips
             WHERE traccar_trip_id IS NOT NULL
               AND id NOT IN (
                   SELECT MIN(id) FROM {$schema}.vehicle_trips
                   WHERE traccar_trip_id IS NOT NULL
                   GROUP BY company_id, traccar_trip_id
               )"
        );

        DB::statement(
            "CREATE UNIQUE INDEX {$indexName}
             ON {$schema}.vehicle_trips (company_id, traccar_trip_id)
             WHERE traccar_trip_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        $schema = (string) (DB::selectOne('SELECT current_schema() AS schema')?->schema ?? 'public');

        if ($schema === '') {
            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$schema}.{$this->indexName()}");
    }

    private function indexName(): string
    {
        return 'vehicle_trips_traccar_trip_id_unique_per_company';
    }
};

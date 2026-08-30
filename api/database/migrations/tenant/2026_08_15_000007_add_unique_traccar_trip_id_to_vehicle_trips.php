<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #3369 — `vehicle_trips.traccar_trip_id` n'a pas d'index unique : deux syncs
 * concurrents de `TrackingSyncController::syncTrips` peuvent insérer des trips
 * doublons (le garde `exists()` n'est pas atomique). On ajoute un index unique
 * partiel (company_id, traccar_trip_id) et on dédoublonne d'abord (garde la
 * ligne la plus ancienne).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vehicle_trips') || ! schemaHasColumn('vehicle_trips', 'traccar_trip_id')) {
            return;
        }

        // Dédoublonnage préalable : ne garder que la plus ancienne ligne par
        // (company_id, traccar_trip_id) non nul.
        DB::statement('
            DELETE FROM vehicle_trips a
            USING vehicle_trips b
            WHERE a.traccar_trip_id IS NOT NULL
              AND a.traccar_trip_id = b.traccar_trip_id
              AND a.company_id IS NOT DISTINCT FROM b.company_id
              AND a.id > b.id
        ');

        // Index unique partiel : ne contraint que les lignes avec un
        // traccar_trip_id (les imports manuels sans id Traccar restent libres).
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS vehicle_trips_company_traccar_trip_unique
            ON vehicle_trips (company_id, traccar_trip_id)
            WHERE traccar_trip_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS vehicle_trips_company_traccar_trip_unique');
    }
};

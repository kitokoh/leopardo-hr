<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8540 (TRAVEL-OS) — dernière position connue d'un véhicule de flotte.
 *
 * Colonnes NULLABLES : un véhicule sans dispositif de localisation n'a
 * simplement aucune position connue. Aucune valeur par défaut n'est posée —
 * une position ne doit jamais être inventée.
 *
 * `position_source` distingue une mesure réelle (`gps`, `app`) d'une
 * estimation (`estimated`) : le tableau de bord ne présente jamais une
 * estimation comme une position GPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_vehicles')) {
            return;
        }

        if (Schema::hasColumn('travel_vehicles', 'latitude')) {
            return;
        }

        Schema::table('travel_vehicles', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('position_source', 20)->nullable();
            $table->timestampTz('position_recorded_at')->nullable();

            $table->index(
                ['company_id', 'position_recorded_at'],
                'travel_vehicles_company_position_idx'
            );
        });

        DB::statement(
            'ALTER TABLE travel_vehicles ADD CONSTRAINT travel_vehicles_latitude_check '
            .'CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))'
        );
        DB::statement(
            'ALTER TABLE travel_vehicles ADD CONSTRAINT travel_vehicles_longitude_check '
            .'CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))'
        );
        DB::statement(
            'ALTER TABLE travel_vehicles ADD CONSTRAINT travel_vehicles_position_source_check '
            ."CHECK (position_source IS NULL OR position_source IN ('gps', 'app', 'estimated'))"
        );
        DB::statement(
            "COMMENT ON COLUMN travel_vehicles.position_source IS "
            ."'Provenance de la derniere position connue : gps | app | estimated (jamais une mesure si estimated) — #8540.'"
        );
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_vehicles')) {
            return;
        }

        if (! Schema::hasColumn('travel_vehicles', 'latitude')) {
            return;
        }

        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS travel_vehicles_latitude_check');
        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS travel_vehicles_longitude_check');
        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS travel_vehicles_position_source_check');

        Schema::table('travel_vehicles', function (Blueprint $table): void {
            $table->dropIndex('travel_vehicles_company_position_idx');
            $table->dropColumn(['latitude', 'longitude', 'position_source', 'position_recorded_at']);
        });
    }
};

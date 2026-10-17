<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8548 (TRAVEL-OS) — historique des positions transmises par un dispositif
 * autorisé (boîtier GPS ou application conducteur).
 *
 * Pourquoi une table dédiée : `travel_vehicles.latitude/longitude` (#8540) ne
 * conserve que la DERNIÈRE position connue — un instantané. Sans historique,
 * aucun itinéraire point-à-point n'est possible, et la détection de retard ne
 * peut pas s'appuyer sur une trace datée.
 *
 * Ce que cette table N'EST PAS : elle ne fabrique aucune position. Une ligne
 * n'existe que si un dispositif a réellement transmis des coordonnées. Un
 * véhicule sans équipement n'a AUCUNE ligne — jamais une ligne « estimée »
 * inventée par le serveur.
 *
 * - `source` reprend l'enum `TravelVehiclePositionSource` (`gps`, `app`,
 *   `estimated`) : une estimation ne peut pas être confondue avec une mesure ;
 * - `recorded_at` = horodatage de la MESURE (transmis par le dispositif, ou
 *   l'instant de réception s'il est absent) — jamais une date devinée ;
 * - `company_id` porte l'isolation (pas de FK vers `companies`, table
 *   publique — conventions migrations tenant §2.6) ;
 * - index `(company_id, vehicle_id, recorded_at)` : la lecture d'historique
 *   est toujours bornée par tenant, véhicule et fenêtre temporelle ;
 * - migration idempotente (`schemaTableExists`) + `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_vehicle_position_logs')) {
            return;
        }

        Schema::create('travel_vehicle_position_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->nullable();
            $table->unsignedBigInteger('vehicle_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source', 20);
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('vehicle_id')
                ->references('id')
                ->on('travel_vehicles')
                ->cascadeOnDelete();

            $table->index(
                ['company_id', 'vehicle_id', 'recorded_at'],
                'travel_vehicle_position_logs_scope_idx'
            );
        });

        DB::statement(
            'ALTER TABLE travel_vehicle_position_logs ADD CONSTRAINT travel_vehicle_position_logs_latitude_check '
            .'CHECK (latitude >= -90 AND latitude <= 90)'
        );
        DB::statement(
            'ALTER TABLE travel_vehicle_position_logs ADD CONSTRAINT travel_vehicle_position_logs_longitude_check '
            .'CHECK (longitude >= -180 AND longitude <= 180)'
        );
        DB::statement(
            'ALTER TABLE travel_vehicle_position_logs ADD CONSTRAINT travel_vehicle_position_logs_source_check '
            ."CHECK (source IN ('gps', 'app', 'estimated'))"
        );
        DB::statement(
            'COMMENT ON TABLE travel_vehicle_position_logs IS '
            ."'Historique des positions REELLEMENT transmises par un dispositif autorise (aucune position fabriquee) — #8548.'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_vehicle_position_logs');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7401 — historique des positions GPS de la flotte : table `vehicle_positions`.
 *
 * Avant cette table, l'endpoint `POST /tracking/sync-positions` s'appelait
 * « sync » mais n'écrivait **rien** (il comptait seulement les véhicules ayant
 * une dernière position connue) : le GPS n'existait qu'en colonnes
 * d'instantané dans `vehicle_trips` / `vehicle_alerts`, ce qui interdit tout
 * suivi d'itinéraire point-à-point. Cette table conserve la trace brute
 * renvoyée par Traccar (`GET /api/positions?deviceId=&from=&to=`).
 *
 * - `traccar_position_id` = identifiant Traccar de la position ; l'unicité
 *   `(company_id, traccar_position_id)` rend la synchronisation **idempotente**
 *   (rejeu d'une même fenêtre = 0 nouvelle ligne, `insertOrIgnore`) ;
 * - `speed_kmh` : Traccar exprime la vitesse en **nœuds**, la conversion
 *   (× 1,852) est faite à l'écriture — le stockage porte l'unité du contrat
 *   API (`km/h`), comme `vehicle_trips.max_speed_kmh` ;
 * - `device_id` = `vehicles.traccar_device_id` au moment de l'écriture
 *   (traçabilité côté Traccar), `vehicle_id` = véhicule interne ;
 * - pas de FK vers `companies` (table publique) — conventions migrations
 *   tenant §2.6 ; l'isolation reste portée par `company_id`
 *   (`BelongsToCompany`) ;
 * - migration idempotente (`schemaTableExists`) + `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vehicle_positions')) {
            Schema::create('vehicle_positions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->nullable();
                $table->unsignedInteger('device_id')->nullable();
                $table->unsignedBigInteger('traccar_position_id')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed_kmh', 6, 2)->nullable();
                $table->timestampTz('recorded_at');
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->unique(
                    ['company_id', 'traccar_position_id'],
                    'vehicle_positions_company_traccar_position_unique',
                );
                $table->index(['vehicle_id', 'recorded_at'], 'vehicle_positions_vehicle_recorded_index');
                $table->index('company_id', 'vehicle_positions_company_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};

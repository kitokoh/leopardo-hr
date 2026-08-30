<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6018 (TRAVEL-205) — travel_vehicles.
 *
 * Flotte propre de l'agence (bus/véhicules détenus par le tenant, distincts
 * de la flotte d'un transporteur tiers) — spec §5.2. `carrier_id` nullable :
 * un véhicule propre n'appartient à aucune compagnie tierce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_vehicles')) {
            return;
        }

        Schema::create('travel_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->string('code', 40);
            $table->string('registration_number', 40)->nullable();
            $table->unsignedInteger('seat_capacity');
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'code'], 'travel_vehicles_company_code_unique');
            $table->index(['company_id', 'carrier_id'], 'travel_vehicles_company_carrier_idx');
        });

        DB::statement('ALTER TABLE travel_vehicles ADD CONSTRAINT travel_vehicles_seat_capacity_check CHECK (seat_capacity > 0)');
        DB::statement("COMMENT ON TABLE travel_vehicles IS 'Flotte propre de l agence — carrier_id nullable (TRAVEL-205/#6018).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_vehicles');
    }
};

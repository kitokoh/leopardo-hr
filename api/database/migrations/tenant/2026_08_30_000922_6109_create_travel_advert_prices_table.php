<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6109 (TRAVEL-906) — Annonces : grille tarifaire.
 *
 * `travel_advert_prices` : prix par image et par caractère en unités
 * mineures, devise (cohérence devise tenant), une grille par
 * (type, position). Contrainte CHECK : montants strictement positifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_advert_prices')) {
            return;
        }

        Schema::create('travel_advert_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('advert_type_id');
            $table->unsignedBigInteger('advert_position_id');
            $table->unsignedInteger('price_per_image_minor');
            $table->unsignedInteger('price_per_character_minor');
            $table->char('currency', 3);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['company_id', 'advert_type_id', 'advert_position_id'], 'travel_advert_prices_company_type_position_unique');
        });

        DB::statement('ALTER TABLE travel_advert_prices ADD CONSTRAINT travel_advert_prices_amounts_check CHECK (price_per_image_minor > 0 AND price_per_character_minor > 0)');
        DB::statement("COMMENT ON TABLE travel_advert_prices IS 'Grille tarifaire des annonces — minor units, devise tenant (TRAVEL-906/#6109).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_advert_prices');
    }
};

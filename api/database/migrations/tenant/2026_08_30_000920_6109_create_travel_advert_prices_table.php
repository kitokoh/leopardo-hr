<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces payantes (spec §3).
 *
 * Prix en unités mineures (minor units) : prix de l'image et prix par
 * caractère, pour un couple (type, position) dans la devise du tenant.
 * Unicité (company_id, advert_type_id, advert_position_id, currency) ;
 * bornes non négatives (CHECK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_advert_prices')) {
            Schema::create('travel_advert_prices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('advert_type_id');
                $table->unsignedBigInteger('advert_position_id');
                $table->unsignedBigInteger('price_image_minor')->default(0);
                $table->unsignedBigInteger('price_character_minor')->default(0);
                $table->char('currency', 3);
                $table->timestamps();
                $table->unique(['company_id', 'advert_type_id', 'advert_position_id', 'currency'], 'travel_advert_prices_unique');
            });

            DB::statement("ALTER TABLE travel_advert_prices ADD CONSTRAINT travel_advert_prices_image_check CHECK (price_image_minor >= 0)");
            DB::statement("ALTER TABLE travel_advert_prices ADD CONSTRAINT travel_advert_prices_character_check CHECK (price_character_minor >= 0)");
            DB::statement("COMMENT ON TABLE travel_advert_prices IS 'Grille tarifaire des annonces (TRAVEL-906/#6109) — minor units.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_advert_prices');
    }
};

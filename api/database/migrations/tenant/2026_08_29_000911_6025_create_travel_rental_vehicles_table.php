<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6025 (TRAVEL-212) — travel_rental_vehicles + travel_rental_vehicle_images.
 *
 * Véhicules en location (spec §5.4). `owner_carrier_id` nullable : un
 * véhicule de location peut appartenir à l'agence elle-même (pas à une
 * compagnie tierce). Positions d'images uniques par véhicule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_rental_vehicles')) {
            Schema::create('travel_rental_vehicles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->string('title', 160);
                $table->unsignedBigInteger('city_id');
                $table->unsignedInteger('price_per_day_minor');
                $table->char('currency', 3);
                $table->date('available_from')->nullable();
                $table->date('available_until')->nullable();
                $table->unsignedBigInteger('owner_carrier_id')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_rental_vehicles_company_code_unique');
                $table->index(['company_id', 'city_id'], 'travel_rental_vehicles_company_city_idx');
            });

            DB::statement('ALTER TABLE travel_rental_vehicles ADD CONSTRAINT travel_rental_vehicles_price_positive_check CHECK (price_per_day_minor > 0)');
            DB::statement("COMMENT ON TABLE travel_rental_vehicles IS 'Vehicules en location de la verticale TravelAgency (TRAVEL-212/#6025).'");
        }

        if (! schemaTableExists('travel_rental_vehicle_images')) {
            Schema::create('travel_rental_vehicle_images', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('vehicle_id');
                $table->unsignedBigInteger('asset_id');
                $table->unsignedSmallInteger('position')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'vehicle_id', 'position'], 'travel_rental_images_company_vehicle_position_unique');
                $table->index(['company_id', 'vehicle_id'], 'travel_rental_images_company_vehicle_idx');
            });

            DB::statement("COMMENT ON TABLE travel_rental_vehicle_images IS 'Images dun vehicule de location — position unique par vehicule (TRAVEL-212/#6025).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_rental_vehicle_images');
        Schema::dropIfExists('travel_rental_vehicles');
    }
};

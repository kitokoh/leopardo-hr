<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6027 (TRAVEL-214) — travel_hotels + travel_hotel_rooms.
 *
 * Catalogue hôtelier (spec §5.4). `classification` bornée à 1-5 étoiles ;
 * `description_redacted` : pas de contenu utilisateur non modéré persisté
 * en clair (cohérent avec la convention `*_redacted` des autres tables
 * TravelAgency). Numéro de chambre unique par hôtel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_hotels')) {
            Schema::create('travel_hotels', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 160);
                $table->unsignedBigInteger('city_id');
                $table->unsignedTinyInteger('classification');
                $table->string('address', 255)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->text('description_redacted')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->index(['company_id', 'city_id'], 'travel_hotels_company_city_idx');
            });

            DB::statement('ALTER TABLE travel_hotels ADD CONSTRAINT travel_hotels_classification_check CHECK (classification BETWEEN 1 AND 5)');
            DB::statement("COMMENT ON TABLE travel_hotels IS 'Catalogue hotelier de la verticale TravelAgency — classification 1-5 (TRAVEL-214/#6027).'");
        }

        if (! schemaTableExists('travel_hotel_rooms')) {
            Schema::create('travel_hotel_rooms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('hotel_id');
                $table->string('type_code', 40);
                $table->string('room_number', 20);
                $table->unsignedInteger('capacity');
                $table->unsignedInteger('price_per_night_minor');
                $table->char('currency', 3);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'hotel_id', 'room_number'], 'travel_hotel_rooms_company_hotel_room_unique');
                $table->index(['company_id', 'hotel_id'], 'travel_hotel_rooms_company_hotel_idx');
            });

            DB::statement('ALTER TABLE travel_hotel_rooms ADD CONSTRAINT travel_hotel_rooms_capacity_check CHECK (capacity > 0)');
            DB::statement('ALTER TABLE travel_hotel_rooms ADD CONSTRAINT travel_hotel_rooms_price_positive_check CHECK (price_per_night_minor > 0)');
            DB::statement("COMMENT ON TABLE travel_hotel_rooms IS 'Chambres dhotel — numero unique par hotel (TRAVEL-214/#6027).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_hotel_rooms');
        Schema::dropIfExists('travel_hotels');
    }
};

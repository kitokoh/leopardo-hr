<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6021 (TRAVEL-208) — travel_trip_seats.
 *
 * Inventaire par siège — invariant de stock (spec §5.2, D4). Générés en
 * transaction à la création du trajet (voir `GenerateTripSeatsAction`),
 * jamais de doublon même en cas de rejeu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_trip_seats')) {
            return;
        }

        Schema::create('travel_trip_seats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('trip_id');
            $table->unsignedInteger('seat_number');
            $table->string('status', 20)->default('free');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->timestamp('reserved_until')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'trip_id', 'seat_number'], 'travel_trip_seats_company_trip_seat_unique');
            $table->index(['company_id', 'trip_id', 'status'], 'travel_trip_seats_company_trip_status_idx');
        });

        DB::statement("ALTER TABLE travel_trip_seats ADD CONSTRAINT travel_trip_seats_status_check CHECK (status IN ('free', 'reserved', 'sold'))");
        DB::statement("COMMENT ON TABLE travel_trip_seats IS 'Inventaire par siege dun trajet — genere en transaction, idempotent (TRAVEL-208/#6021).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_trip_seats');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6020 (TRAVEL-207) — travel_trips + travel_trip_prices.
 *
 * Instances datées d'une route (spec §5.2) et tarifs par classe en unités
 * mineures (minor units) — jamais de flottant sur un montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_trips')) {
            Schema::create('travel_trips', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->unsignedBigInteger('route_id');
                $table->unsignedBigInteger('carrier_id')->nullable();
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->date('departure_date');
                $table->time('departure_time');
                $table->date('arrival_date');
                $table->time('arrival_time');
                $table->string('means_of_transport', 20)->default('bus');
                $table->unsignedInteger('total_seats');
                $table->string('status', 20)->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_trips_company_code_unique');
                $table->index(['route_id', 'departure_date'], 'travel_trips_route_departure_idx');
                $table->index(['company_id', 'status'], 'travel_trips_company_status_idx');
            });

            DB::statement("ALTER TABLE travel_trips ADD CONSTRAINT travel_trips_status_check CHECK (status IN ('draft', 'scheduled', 'published', 'cancelled'))");
            DB::statement('ALTER TABLE travel_trips ADD CONSTRAINT travel_trips_total_seats_check CHECK (total_seats > 0)');
            DB::statement("COMMENT ON TABLE travel_trips IS 'Instances datees dune route de la verticale TravelAgency (TRAVEL-207/#6020).'");
        }

        if (! schemaTableExists('travel_trip_prices')) {
            Schema::create('travel_trip_prices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trip_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedInteger('adult_price_minor');
                $table->unsignedInteger('child_price_minor')->nullable();
                $table->char('currency', 3);

                $table->timestamps();

                $table->unique(['company_id', 'trip_id', 'class_id'], 'travel_trip_prices_company_trip_class_unique');
            });

            DB::statement('ALTER TABLE travel_trip_prices ADD CONSTRAINT travel_trip_prices_adult_price_positive_check CHECK (adult_price_minor > 0)');
            DB::statement('ALTER TABLE travel_trip_prices ADD CONSTRAINT travel_trip_prices_child_price_positive_check CHECK (child_price_minor IS NULL OR child_price_minor > 0)');
            DB::statement("COMMENT ON TABLE travel_trip_prices IS 'Tarifs par trajet/classe en unites mineures — un seul prix par (trip, classe) (TRAVEL-207/#6020).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_trip_prices');
        Schema::dropIfExists('travel_trips');
    }
};

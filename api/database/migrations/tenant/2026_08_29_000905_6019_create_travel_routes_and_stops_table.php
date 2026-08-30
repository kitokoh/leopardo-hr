<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6019 (TRAVEL-206) — travel_routes + travel_route_stops.
 *
 * Lignes ville→ville (spec §5.2) et leurs étapes ordonnées (escales). Une
 * route ne peut relier une ville à elle-même ; les étapes sont ordonnées par
 * `rank` et une même ville ne peut apparaître deux fois sur une route.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_routes')) {
            Schema::create('travel_routes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->unsignedBigInteger('origin_city_id');
                $table->unsignedBigInteger('destination_city_id');
                $table->unsignedInteger('distance_km')->nullable();
                $table->unsignedInteger('duration_min')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_routes_company_code_unique');
                $table->unique(
                    ['company_id', 'origin_city_id', 'destination_city_id'],
                    'travel_routes_company_origin_destination_unique'
                );
                $table->index(['company_id', 'origin_city_id'], 'travel_routes_company_origin_idx');
                $table->index(['company_id', 'destination_city_id'], 'travel_routes_company_destination_idx');
            });

            DB::statement('ALTER TABLE travel_routes ADD CONSTRAINT travel_routes_origin_destination_distinct_check CHECK (origin_city_id <> destination_city_id)');
            DB::statement("COMMENT ON TABLE travel_routes IS 'Lignes ville a ville de la verticale TravelAgency (TRAVEL-206/#6019).'");
        }

        if (! schemaTableExists('travel_route_stops')) {
            Schema::create('travel_route_stops', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('route_id');
                $table->unsignedBigInteger('city_id');
                $table->unsignedSmallInteger('rank');
                $table->boolean('is_stopover')->default(true);
                $table->unsignedInteger('min_duration_min')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'route_id', 'rank'], 'travel_route_stops_company_route_rank_unique');
                $table->unique(['company_id', 'route_id', 'city_id'], 'travel_route_stops_company_route_city_unique');
                $table->index(['company_id', 'route_id'], 'travel_route_stops_company_route_idx');
            });

            DB::statement("COMMENT ON TABLE travel_route_stops IS 'Etapes ordonnees dune route (rank) — pas de ville dupliquee (TRAVEL-206/#6019).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_route_stops');
        Schema::dropIfExists('travel_routes');
    }
};

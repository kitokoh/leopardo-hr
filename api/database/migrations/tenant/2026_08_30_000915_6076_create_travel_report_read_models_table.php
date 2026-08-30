<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-506 (#6076) — Read models recalculables des rapports travel.
 *
 * `travel_daily_sales`    : ventes journalières par trajet (bookings, passagers, CA) ;
 * `travel_trip_occupancy` : occupation par trajet (sièges vendus / capacité).
 * Recalculés par job idempotent (RecalculateTravelReadModelsCommand) — une
 * reprise produit le MÊME état (mise à jour par clé unique, jamais d'insert
 * en double).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_daily_sales')) {
            Schema::create('travel_daily_sales', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->date('sale_date');
                $table->unsignedBigInteger('trip_id');
                $table->unsignedInteger('bookings_count')->default(0);
                $table->unsignedInteger('passengers_count')->default(0);
                $table->unsignedInteger('revenue_minor')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'sale_date', 'trip_id'], 'travel_daily_sales_company_date_trip_unique');
            });

            DB::statement("COMMENT ON TABLE travel_daily_sales IS 'Ventes journalières par trajet - read model recalculable (TRAVEL-506/#6076).'");
        }

        if (! schemaTableExists('travel_trip_occupancy')) {
            Schema::create('travel_trip_occupancy', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('trip_id');
                $table->date('departure_date');
                $table->unsignedInteger('seats_sold')->default(0);
                $table->unsignedInteger('total_seats')->default(0);
                $table->decimal('occupancy_rate', 5, 4)->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'trip_id'], 'travel_trip_occupancy_company_trip_unique');
            });

            DB::statement("COMMENT ON TABLE travel_trip_occupancy IS 'Occupation par trajet - read model recalculable (TRAVEL-506/#6076).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_daily_sales');
        Schema::dropIfExists('travel_trip_occupancy');
    }
};

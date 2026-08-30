<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6076 (TRAVEL-506) — Read models des rapports TravelAgency.
 *
 * Deux tables de lecture recalculables par des jobs idempotents :
 * - `travel_daily_sales` : ventes journalières agrégées (source, statut,
 *   passagers, montants en minor units) — la reprise du job donne un état
 *   identique (upsert par clé naturelle, pas d'accumulation).
 * - `travel_trip_occupancy` : occupation par trajet (sièges vendus/total,
 *   taux) — mêmes garanties.
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
                $table->string('source', 20);
                $table->string('status', 20);
                $table->unsignedInteger('booking_count');
                $table->unsignedInteger('passenger_count');
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3);
                $table->timestamps();

                $table->unique(
                    ['company_id', 'sale_date', 'source', 'status', 'currency'],
                    'travel_daily_sales_natural_unique',
                );
            });
        }

        if (! schemaTableExists('travel_trip_occupancy')) {
            Schema::create('travel_trip_occupancy', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('trip_id');
                $table->date('departure_date');
                $table->unsignedInteger('total_seats');
                $table->unsignedInteger('sold_seats');
                $table->unsignedInteger('reserved_seats');
                $table->unsignedInteger('free_seats');
                $table->decimal('occupancy_rate', 5, 4)->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'trip_id'], 'travel_trip_occupancy_natural_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_trip_occupancy');
        Schema::dropIfExists('travel_daily_sales');
    }
};

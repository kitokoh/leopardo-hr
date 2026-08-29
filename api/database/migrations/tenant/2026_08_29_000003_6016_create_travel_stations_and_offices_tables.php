<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6016 (TRAVEL-203) — travel_stations + travel_offices.
 *
 * Gares/terminaux (départ & arrivée des trajets) et bureaux de vente
 * (guichets). Les deux référencent une ville du référentiel tenant-scoped ;
 * code unique de gare par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_stations')) {
            Schema::create('travel_stations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->string('name', 120);
                $table->unsignedBigInteger('city_id');
                $table->string('address', 255)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->string('timezone', 50)->default('UTC');
                $table->boolean('is_terminal')->default(false);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_stations_company_code_unique');
                $table->index(['company_id', 'city_id'], 'travel_stations_company_city_idx');
            });

            DB::statement("COMMENT ON TABLE travel_stations IS 'Gares/terminaux de la verticale TravelAgency — code unique par tenant (TRAVEL-203/#6016).'");
        }

        if (! schemaTableExists('travel_offices')) {
            Schema::create('travel_offices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 120);
                $table->unsignedBigInteger('city_id');
                $table->string('address', 255)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->index(['company_id', 'city_id'], 'travel_offices_company_city_idx');
            });

            DB::statement("COMMENT ON TABLE travel_offices IS 'Bureaux de vente de l agence — tenant-scoped (TRAVEL-203/#6016).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_offices');
        Schema::dropIfExists('travel_stations');
    }
};

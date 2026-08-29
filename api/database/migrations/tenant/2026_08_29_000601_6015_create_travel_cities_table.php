<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6015 (TRAVEL-202) — travel_cities : villes du référentiel, tenant-scoped.
 *
 * Rattachement au pays par code ISO2 (pas de FK inter-tenant). Unicité
 * (company_id, country_iso2, name) pour garantir l'idempotence du seed
 * (insertOrIgnore). `region` = découpage de premier niveau ; découpages à
 * 3 niveaux planifiés en Phase 2 (spec §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_cities')) {
            Schema::create('travel_cities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->char('country_iso2', 2);
                $table->string('name', 120);
                $table->string('region', 120)->nullable();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'country_iso2', 'name'], 'travel_cities_company_country_name_unique');
                $table->index(['company_id', 'country_iso2'], 'travel_cities_company_country_idx');
                $table->index(['company_id', 'name'], 'travel_cities_company_name_idx');
            });

            DB::statement("COMMENT ON TABLE travel_cities IS 'Villes du référentiel TravelAgency — tenant-scoped, seed idempotent (TRAVEL-202/#6015).'");
            DB::statement("COMMENT ON COLUMN travel_cities.status IS 'active|disabled (enum TravelRecordStatus).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_cities');
    }
};

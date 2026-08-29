<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6014 (TRAVEL-201) — travel_countries : référentiel des pays, tenant-scoped.
 *
 * Référentiel ISO 3166-1 seedé au provisioning (TravelGeoSeederService) ;
 * chaque tenant possède son propre jeu (company_id non nullable), modifiable
 * via l'API (statut actif/désactivé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_countries')) {
            Schema::create('travel_countries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->char('iso2', 2);
                $table->char('iso3', 3);
                $table->string('name', 120);
                $table->unsignedSmallInteger('phone_code')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'iso2'], 'travel_countries_company_iso2_unique');
                $table->index(['company_id', 'name'], 'travel_countries_company_name_idx');
            });

            DB::statement("COMMENT ON TABLE travel_countries IS 'Référentiel des pays de la verticale TravelAgency — tenant-scoped, seedé au provisioning (TRAVEL-201/#6014).'");
            DB::statement("COMMENT ON COLUMN travel_countries.status IS 'active|disabled (enum TravelRecordStatus).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_countries');
    }
};

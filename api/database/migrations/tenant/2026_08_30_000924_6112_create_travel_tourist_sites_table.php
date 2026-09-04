<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6112 (TRAVEL-909) — Sites touristiques (annuaire legacy gv-back).
 *
 * `travel_tourist_sites` : nom, description redigée, ville (référentiel
 * tenant-scoped), coordonnées, image, statut. Recherche par ville.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_tourist_sites')) {
            return;
        }

        Schema::create('travel_tourist_sites', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('name', 160);
            $table->string('description_redacted', 2000)->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('image_asset_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->index(['company_id', 'city_id'], 'travel_tourist_sites_company_city_idx');
        });

        DB::statement("COMMENT ON TABLE travel_tourist_sites IS 'Annuaire des sites touristiques — recherche par ville (TRAVEL-909/#6112).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_tourist_sites');
    }
};

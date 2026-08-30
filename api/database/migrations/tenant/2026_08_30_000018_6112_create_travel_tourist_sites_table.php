<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6112 (TRAVEL-909) — Sites touristiques (annuaire géolocalisé).
 *
 * Nom, description redacted (pas de PII), ville, coordonnées géo,
 * images, statut. Recherche par ville (critère d'acceptation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_tourist_sites')) {
            Schema::create('travel_tourist_sites', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 200);
                $table->text('description_redacted')->nullable();
                $table->unsignedBigInteger('city_id');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->json('images')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['company_id', 'city_id'], 'travel_tourist_sites_company_city_idx');
                $table->index(['company_id', 'status'], 'travel_tourist_sites_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_tourist_sites');
    }
};

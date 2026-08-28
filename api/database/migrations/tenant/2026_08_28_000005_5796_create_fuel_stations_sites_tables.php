<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5796 — FuelStation : stations et sites opérationnels.
 *
 * `fuel_stations` : entité légale (enseigne, adresse, pays, statut, fuseau).
 * `fuel_sites` : point opérationnel rattaché à une station (site de vente).
 *
 * Contraintes tenant-first : `company_id` non nullable partout, codes
 * uniques PAR TENANT (clés composites), index `(company_id, ...)` sur
 * chaque colonne de scope, archivage soft (`archived_at`), garde
 * schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_stations')) {
            Schema::create('fuel_stations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 160);
                $table->string('address', 255)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('country', 2)->nullable();          // ISO 3166-1 alpha-2
                $table->string('timezone', 64)->default('UTC');
                $table->string('status', 20)->default('draft');    // draft|active|suspended|closed
                $table->json('metadata')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_stations_company_code_unique');
                $table->index(['company_id', 'status'], 'fuel_stations_company_status_index');
            });
        }

        if (! schemaTableExists('fuel_sites')) {
            Schema::create('fuel_sites', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('station_id')->index();
                $table->string('code', 40);
                $table->string('name', 160);
                $table->string('address', 255)->nullable();
                $table->string('city', 120)->nullable();
                $table->decimal('geo_lat', 10, 7)->nullable();
                $table->decimal('geo_lng', 10, 7)->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_sites_company_code_unique');
                $table->index(['company_id', 'station_id'], 'fuel_sites_company_station_index');
                $table->index(['company_id', 'status'], 'fuel_sites_company_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_sites');
        Schema::dropIfExists('fuel_stations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5796 (FUEL-002) — stations (sites opérationnels) FuelStation.
 *
 * Une station-service est le site opérationnel de la solution : adresse,
 * fuseau, statut. Toutes les données métier FuelStation sont
 * tenant-scoped (`company_id` non nullable) ; le code station est unique
 * PAR tenant (même code possible chez deux tenants différents).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('fuel_stations')) {
            return;
        }

        Schema::create('fuel_stations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->string('timezone', 64)->default('UTC');

            // active | inactive | archived
            $table->string('status', 20)->default('active')->index();

            $table->timestamps();

            $table->unique(['company_id', 'code'], 'fuel_stations_company_code_unique');
            $table->index(['company_id', 'status'], 'fuel_stations_company_status_idx');
        });

        $schema = resolveTableSchema('fuel_stations');

        if ($schema !== null) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_stations ADD CONSTRAINT fuel_stations_status_check CHECK (status IN ('active', 'inactive', 'archived'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stations');
    }
};

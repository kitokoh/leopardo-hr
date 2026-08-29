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
            $table->string('phone', 40)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('currency', 10)->nullable();
            // Métadonnées opérationnelles chiffrées (RGPD) — cast encrypted:array.
            $table->text('metadata')->nullable();

            // active | inactive | archived
            $table->string('status', 20)->default('active')->index();

            $table->timestamps();

            $table->unique(['company_id', 'code'], 'fuel_stations_company_code_unique');
            $table->unique(['id', 'company_id'], 'fuel_stations_id_company_unique');
            $table->index(['company_id', 'status'], 'fuel_stations_company_status_idx');
            $table->index(['company_id', 'created_at'], 'fuel_stations_company_created_idx');
        });

        // FUEL-002 — sites opérationnels d'une station (1 station -> N sites).
        if (! schemaTableExists('fuel_sites')) {
            Schema::create('fuel_sites', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');

                $table->string('code', 40);
                $table->string('name', 150);
                $table->string('address', 255)->nullable();

                // active | inactive
                $table->string('status', 20)->default('active');

                // Métadonnées opérationnelles chiffrées (RGPD).
                $table->text('metadata')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_sites_company_code_unique');
                $table->index(['company_id', 'station_id'], 'fuel_sites_company_station_idx');
                $table->index(['company_id', 'status'], 'fuel_sites_company_status_idx');

                // Cross-tenant impossible : FK composite (station_id, company_id).
                $table->foreign(['station_id', 'company_id'], 'fuel_sites_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            $siteSchema = resolveTableSchema('fuel_sites');

            if ($siteSchema !== null && ! $this->constraintExists('fuel_sites_status_check')) {
                DB::statement(
                    "ALTER TABLE {$siteSchema}.fuel_sites ADD CONSTRAINT fuel_sites_status_check CHECK (status IN ('active', 'inactive'))"
                );
            }
        }

        $schema = resolveTableSchema('fuel_stations');

        if ($schema !== null && ! $this->constraintExists('fuel_stations_status_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_stations ADD CONSTRAINT fuel_stations_status_check CHECK (status IN ('active', 'inactive', 'archived'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_sites');
        Schema::dropIfExists('fuel_stations');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};

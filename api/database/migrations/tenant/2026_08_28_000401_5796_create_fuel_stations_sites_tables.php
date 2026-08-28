<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module FuelStation — Issue #5796 (FUEL-002).
 *
 * Tables tenant (`shared_tenants`) : stations et sites opérationnels.
 *
 * Règles :
 *   - migration additive et idempotente (garde schemaTableExists) ;
 *   - company_id uuid NON nullable sur CHAQUE table — aucune ligne sans tenant,
 *     isolation portée par BelongsToCompany (fail-closed #3727) ;
 *   - clés composites (id, company_id) sur la table parente et FK composites
 *     sur la table fille : toute référence cross-tenant est impossible (pattern
 *     CRM #5709) ;
 *   - statuts contraints par CHECK nommés et documentés ;
 *   - indexes tenant-first (company_id en tête) pour toutes les listes API.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_stations')) {
            Schema::create('fuel_stations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 40);
                $table->string('name', 150);
                $table->text('address')->nullable();
                $table->string('phone', 40)->nullable();
                // active | inactive | maintenance | closed
                $table->string('status', 20)->default('active');
                $table->string('timezone', 60)->default('UTC');
                $table->string('currency', 10)->nullable();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_stations_company_code_unique');
                $table->unique(['id', 'company_id'], 'fuel_stations_id_company_unique');
                $table->index(['company_id', 'status'], 'fuel_stations_company_status_idx');
                $table->index(['company_id', 'created_at'], 'fuel_stations_company_created_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_stations ADD CONSTRAINT fuel_stations_status_check '
                ."CHECK (status IN ('active','inactive','maintenance','closed'))"
            );
        }

        if (! schemaTableExists('fuel_sites')) {
            Schema::create('fuel_sites', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('station_id');
                $table->string('code', 40);
                $table->string('name', 150);
                $table->text('address')->nullable();
                // active | inactive
                $table->string('status', 20)->default('active');
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                // Cross-tenant impossible : un site référence une station du MÊME tenant.
                $table->foreign(['station_id', 'company_id'], 'fuel_sites_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->unique(['company_id', 'code'], 'fuel_sites_company_code_unique');
                $table->index(['company_id', 'station_id'], 'fuel_sites_company_station_idx');
                $table->index(['company_id', 'status'], 'fuel_sites_company_status_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_sites ADD CONSTRAINT fuel_sites_status_check '
                ."CHECK (status IN ('active','inactive'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_sites');
        Schema::dropIfExists('fuel_stations');
    }
};

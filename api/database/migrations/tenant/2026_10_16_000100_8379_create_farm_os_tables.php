<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — socle de la verticale agricole (BC-37 AGRICULTURE).
 *
 * Tranche 1 : exploitations/sites, parcelles, activites declarees, outils
 * activables (capacites), equipes operationnelles, cycles culturaux et
 * operations agricoles (interventions).
 *
 * Conventions respectees (docs/architecture + lecons CI) :
 *   - `company_id` uuid NULLABLE + indexe (NULL en mode schema isole) sur
 *     CHAQUE table : isolation multi-tenant ;
 *   - references internes par identifiant stable SANS cle etrangere
 *     (conventions migrations tenant §2.6) ;
 *   - `timestampTz` partout ;
 *   - `up()` idempotent (`schemaTableExists`) — Render rejoue les migrations ;
 *   - `down()` ne detruit QUE ce que ce fichier a cree (garde #8207).
 *
 * Provenance des superficies (ADR-FARM-0003) : `area_source` ∈
 * declared|imported|measured — Farm OS ne pretend jamais mesurer une superficie
 * saisie a la main. La geometrie reste NULLABLE (`geometry_wkt`) : sans elle,
 * aucune vue carte ni superficie « mesuree » n'est produite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('farm_sites')) {
            Schema::create('farm_sites', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 160);
                $table->string('reference', 40);
                $table->string('kind', 24)->default('production');

                $table->char('country_code', 2)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('address', 240)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('timezone', 64)->default('UTC');

                $table->decimal('total_area_value', 14, 4)->nullable();
                $table->string('total_area_unit', 8)->nullable();
                $table->string('total_area_source', 16)->nullable();

                $table->string('status', 16)->default('active');
                $table->jsonb('metadata')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'reference'], 'farm_sites_company_reference_unique');
                $table->index(['company_id', 'status'], 'farm_sites_company_status_index');
            });
        }

        if (! schemaTableExists('farm_activities')) {
            Schema::create('farm_activities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('activity', 40);
                $table->boolean('is_primary')->default(false);
                $table->timestampTz('declared_at');
                $table->timestampTz('ended_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'activity'], 'farm_activities_company_activity_unique');
            });
        }

        if (! schemaTableExists('farm_capabilities')) {
            Schema::create('farm_capabilities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('capability', 40);
                $table->boolean('enabled')->default(false);
                $table->string('source', 16)->default('settings');
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestampTz('decided_at');
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'capability'], 'farm_capabilities_company_capability_unique');
            });
        }

        if (! schemaTableExists('farm_teams')) {
            Schema::create('farm_teams', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_site_id')->nullable();
                $table->string('name', 120);
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'name'], 'farm_teams_company_name_unique');
            });
        }

        if (! schemaTableExists('farm_team_members')) {
            Schema::create('farm_team_members', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_team_id');
                $table->unsignedBigInteger('employee_id');
                $table->timestampTz('joined_at');
                $table->timestampTz('left_at')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'farm_team_id', 'employee_id'], 'farm_team_members_unique');
            });
        }

        if (! schemaTableExists('farm_parcels')) {
            Schema::create('farm_parcels', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_site_id');
                $table->string('name', 160);
                $table->string('reference', 40);

                // Superficie + PROVENANCE (ADR-FARM-0003) : jamais de valeur
                // « mesuree » sans geometrie stockee.
                $table->decimal('area_value', 14, 4)->nullable();
                $table->string('area_unit', 8)->nullable();
                $table->string('area_source', 16)->nullable();

                // Geometrie cartographique NULLABLE : sans elle, aucune vue
                // carte ni calcul de surface n'est produit.
                $table->text('geometry_wkt')->nullable();
                $table->string('geometry_source', 16)->nullable();

                $table->string('soil_type', 60)->nullable();
                $table->string('irrigation_type', 40)->nullable();
                $table->string('status', 16)->default('active');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'reference'], 'farm_parcels_company_reference_unique');
                $table->index(['company_id', 'farm_site_id', 'status'], 'farm_parcels_site_status_index');
            });
        }

        if (! schemaTableExists('farm_crop_cycles')) {
            Schema::create('farm_crop_cycles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('parcel_id');
                $table->string('crop_code', 60);
                $table->string('crop_variety', 80)->nullable();
                $table->string('season_label', 40)->nullable();

                $table->date('planned_sowing_on')->nullable();
                $table->date('actual_sowing_on')->nullable();
                $table->date('planned_harvest_on')->nullable();
                $table->date('actual_harvest_on')->nullable();

                $table->decimal('expected_yield_value', 14, 4)->nullable();
                $table->string('expected_yield_unit', 16)->nullable();
                $table->decimal('actual_yield_value', 14, 4)->nullable();
                $table->string('actual_yield_unit', 16)->nullable();

                $table->string('status', 16)->default('planned');
                $table->text('notes')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->index(['company_id', 'parcel_id', 'status'], 'farm_crop_cycles_parcel_status_index');
                $table->index(['company_id', 'actual_sowing_on'], 'farm_crop_cycles_sowing_index');
            });
        }

        if (! schemaTableExists('farm_operations')) {
            Schema::create('farm_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_site_id');
                $table->unsignedBigInteger('parcel_id')->nullable();
                $table->unsignedBigInteger('crop_cycle_id')->nullable();

                $table->string('op_type', 40);
                $table->string('title', 160);
                $table->text('instructions')->nullable();
                $table->string('status', 16)->default('planned');
                $table->string('priority', 8)->default('normal');

                $table->timestampTz('planned_start_at')->nullable();
                $table->timestampTz('planned_end_at')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();

                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('farm_team_id')->nullable();
                $table->unsignedBigInteger('responsible_id')->nullable();

                $table->decimal('quantity_value', 14, 4)->nullable();
                $table->string('quantity_unit', 16)->nullable();
                $table->decimal('progress_percent', 5, 2)->nullable();
                $table->text('notes')->nullable();

                // Idempotence terrain : le rejeu d'une ecriture mobile ou hors
                // ligne renvoie l'operation existante au lieu d'en creer une 2e.
                $table->string('client_operation_id', 64)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_operation_id'], 'farm_operations_client_operation_unique');
                $table->index(['company_id', 'status', 'planned_start_at'], 'farm_operations_status_planned_index');
                $table->index(['company_id', 'parcel_id', 'planned_start_at'], 'farm_operations_parcel_planned_index');
                $table->index(['company_id', 'assigned_to', 'status'], 'farm_operations_assignee_status_index');
            });
        }
    }

    public function down(): void
    {
        // Non destructif au-dela de ce que CE fichier a cree : ordre inverse des
        // dependances logiques (aucune cle etrangere n'est posee).
        Schema::dropIfExists('farm_operations');
        Schema::dropIfExists('farm_crop_cycles');
        Schema::dropIfExists('farm_parcels');
        Schema::dropIfExists('farm_team_members');
        Schema::dropIfExists('farm_teams');
        Schema::dropIfExists('farm_capabilities');
        Schema::dropIfExists('farm_activities');
        Schema::dropIfExists('farm_sites');
    }
};

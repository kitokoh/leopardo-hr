<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5797 — FuelStation : équipements, pompes, cuves, compteurs.
 *
 * `fuel_equipment` : registre générique des équipements par site (type
 * allowlisté pump|tank|meter|nozzle|other).
 * `fuel_pumps` : pompes de distribution par site.
 * `fuel_tanks` : cuves (produit allowlisté, capacité, unité l|m3).
 * `fuel_meters` : compteurs rattachés à une pompe — **au plus un compteur
 * actif par pompe** (index unique partiel `(pump_id)` WHERE is_active).
 *
 * Références cross-tenant impossibles : les FK ne sont PAS déclarées en base
 * (pattern monorepo : isolation par company_id + scopes), l'unicité des
 * codes est composite (company_id, ...). Garde schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_equipment')) {
            Schema::create('fuel_equipment', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('site_id')->index();
                $table->string('type', 20);                       // pump|tank|meter|nozzle|other
                $table->string('code', 40);
                $table->string('name', 160)->nullable();
                $table->string('status', 20)->default('active');  // active|maintenance|retired
                $table->timestamp('installed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_equipment_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_equipment_company_site_index');
                $table->index(['company_id', 'type'], 'fuel_equipment_company_type_index');
            });
        }

        if (! schemaTableExists('fuel_pumps')) {
            Schema::create('fuel_pumps', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('site_id')->index();
                $table->uuid('equipment_id')->nullable()->index();
                $table->string('code', 40);
                $table->string('name', 160)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_pumps_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_pumps_company_site_index');
            });
        }

        if (! schemaTableExists('fuel_tanks')) {
            Schema::create('fuel_tanks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('site_id')->index();
                $table->string('code', 40);
                $table->string('product', 30);                    // diesel|essence_95|essence_98|gpl|lubrifiant|autre
                $table->decimal('capacity', 12, 2);
                $table->string('unit', 10)->default('l');         // l|m3
                $table->decimal('current_level', 12, 2)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_tanks_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_tanks_company_site_index');
                $table->index(['company_id', 'product'], 'fuel_tanks_company_product_index');
            });
        }

        if (! schemaTableExists('fuel_meters')) {
            Schema::create('fuel_meters', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('pump_id')->index();
                $table->string('code', 40);
                $table->string('unit', 10)->default('l');         // l|m3
                $table->boolean('is_active')->default(true);
                $table->string('status', 20)->default('active');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_meters_company_code_unique');
                $table->index(['company_id', 'pump_id'], 'fuel_meters_company_pump_index');
                // Au plus un compteur actif par pompe (index partiel PG).
                $table->unique(['pump_id'], 'fuel_meters_pump_active_unique')->where('is_active', true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_meters');
        Schema::dropIfExists('fuel_tanks');
        Schema::dropIfExists('fuel_pumps');
        Schema::dropIfExists('fuel_equipment');
    }
};

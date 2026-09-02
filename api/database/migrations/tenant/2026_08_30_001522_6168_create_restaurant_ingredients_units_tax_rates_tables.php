<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6168 (RESTO-203) - RestaurantManager : ingredients, unites, taux de TVA.
 *
 * - `restaurant_ingredients` : matieres premieres (code unique par tenant/branche) ;
 * - `restaurant_units` : referentiel d unites (g, kg, l, u...) ;
 * - `restaurant_tax_rates` : taux de taxe en basis points (rate_bps), is_default.
 *
 * Montants en minor units, taux en basis points (entiers). Tenant-scoped, sans FK.
 * Idempotente (garde `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_ingredients')) {
            Schema::create('restaurant_ingredients', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->string('unit_code', 20);
                $table->unsignedInteger('avg_cost_minor')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'branch_id', 'code'], 'restaurant_ingredients_company_branch_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_ingredients IS 'Matieres premieres - code unique par (tenant, branche), branch_id null = toutes branches (RESTO-203/#6168).';");
        }

        if (! schemaTableExists('restaurant_units')) {
            Schema::create('restaurant_units', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 20);
                $table->string('label', 80);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_units_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_units IS 'Referentiel d unites de mesure - code unique par tenant (RESTO-203/#6168).';");
        }

        if (! schemaTableExists('restaurant_tax_rates')) {
            Schema::create('restaurant_tax_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 20);
                $table->string('label', 80);
                $table->unsignedInteger('rate_bps')->default(0);
                $table->boolean('is_default')->default(false);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_tax_rates_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_tax_rates IS 'Taux de taxe en basis points - code unique par tenant (RESTO-203/#6168).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tax_rates');
        Schema::dropIfExists('restaurant_units');
        Schema::dropIfExists('restaurant_ingredients');
    }
};

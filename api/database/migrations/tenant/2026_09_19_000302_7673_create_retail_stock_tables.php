<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7673 (BC-17 RETAIL) - Gestion de stock moderne du module vendeur :
 * emplacements, niveaux de stock, mouvements d'inventaire traces.
 *
 * - `retail_locations` : emplacements de stock (boutique `store` ou
 *   entrepot `warehouse`), code unique par tenant ;
 * - `retail_stock_levels` : stock courant par (emplacement, produit),
 *   quantites decimales, cout moyen en minor units, seuils de
 *   reapprovisionnement (`reorder_level`) et d'alerte (`alert_threshold`) ;
 * - `retail_inventory_movements` : journal des mouvements (delta signe,
 *   reason_code controle, reference polymorphe reference_type/reference_id).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern RestaurantManager #6170). Idempotente +
 * down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_locations')) {
            Schema::create('retail_locations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 160);
                $table->string('code', 40);
                $table->string('type', 20)->default('store');
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'retail_locations_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE retail_locations IS 'Emplacements de stock du module Retail - code unique par tenant, type store|warehouse (BC-17/#7673).';");
        }

        if (! schemaTableExists('retail_stock_levels')) {
            Schema::create('retail_stock_levels', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 12, 3)->default(0);
                $table->unsignedBigInteger('avg_cost_minor')->nullable();
                $table->decimal('reorder_level', 12, 3)->nullable();
                $table->decimal('alert_threshold', 12, 3)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'location_id', 'product_id'], 'retail_stock_levels_company_location_product_unique');
                $table->index(['company_id', 'product_id'], 'retail_stock_levels_company_product_idx');
            });

            DB::statement("COMMENT ON TABLE retail_stock_levels IS 'Stock courant par (tenant, emplacement, produit) - unique, quantites decimales, seuils d''alerte (BC-17/#7673).';");
        }

        if (! schemaTableExists('retail_inventory_movements')) {
            Schema::create('retail_inventory_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('stock_level_id')->nullable();
                $table->decimal('quantity_delta', 12, 3);
                $table->string('reason_code', 30);
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'location_id', 'product_id'], 'retail_inventory_movements_company_location_product_idx');
                $table->index(['company_id', 'reference_type', 'reference_id'], 'retail_inventory_movements_company_reference_idx');
            });

            DB::statement("COMMENT ON TABLE retail_inventory_movements IS 'Journal des mouvements de stock Retail - delta signe, reason_code controle, reference polymorphe (BC-17/#7673).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_inventory_movements')) {
            Schema::dropIfExists('retail_inventory_movements');
        }

        if (schemaTableExists('retail_stock_levels')) {
            Schema::dropIfExists('retail_stock_levels');
        }

        if (schemaTableExists('retail_locations')) {
            Schema::dropIfExists('retail_locations');
        }
    }
};

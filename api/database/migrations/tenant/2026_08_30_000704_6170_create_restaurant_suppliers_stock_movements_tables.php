<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6170 (RESTO-205) - RestaurantManager : fournisseurs, stocks, mouvements.
 *
 * - `restaurant_suppliers` : fournisseurs (nom indexe par tenant) ;
 * - `restaurant_stock_levels` : stock courant par (branche, ingredient), quantites decimales ;
 * - `restaurant_inventory_movements` : journal des mouvements (delta signe, reason_code,
 *   reference polymorphe reference_type/reference_id).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_suppliers')) {
            Schema::create('restaurant_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 150);
                $table->string('contact_phone', 40)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->index(['company_id', 'name'], 'restaurant_suppliers_company_name_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_suppliers IS 'Fournisseurs de la verticale RestaurantManager (RESTO-205/#6170).';");
        }

        if (! schemaTableExists('restaurant_stock_levels')) {
            Schema::create('restaurant_stock_levels', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 12, 3)->default(0);
                $table->unsignedInteger('avg_cost_minor')->nullable();
                $table->decimal('reorder_level', 12, 3)->nullable();
                $table->decimal('alert_threshold', 12, 3)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'branch_id', 'ingredient_id'], 'restaurant_stock_levels_company_branch_ingredient_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_stock_levels IS 'Stock courant par (tenant, branche, ingredient) - unique, quantites decimales (RESTO-205/#6170).';");
        }

        if (! schemaTableExists('restaurant_inventory_movements')) {
            Schema::create('restaurant_inventory_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->unsignedBigInteger('stock_level_id')->nullable();
                $table->decimal('quantity_delta', 12, 3);
                $table->string('reason_code', 30);
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('note_redacted')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'branch_id', 'ingredient_id'], 'restaurant_inventory_movements_company_branch_ingredient_idx');
                $table->index(['company_id', 'reference_type', 'reference_id'], 'restaurant_inventory_movements_company_reference_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_inventory_movements IS 'Journal des mouvements de stock - delta signe, reference polymorphe (RESTO-205/#6170).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_inventory_movements');
        Schema::dropIfExists('restaurant_stock_levels');
        Schema::dropIfExists('restaurant_suppliers');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6167 (RESTO-202) - RestaurantManager : categories, produits, fiches techniques.
 *
 * - `restaurant_categories` : categories de produits (branch_id null = toutes branches) ;
 * - `restaurant_products` : produits a la carte, prix en minor units (price_minor),
 *   description champ `_redacted` (donnees sensibles non conservees en clair) ;
 * - `restaurant_product_ingredients` : fiche technique (composants d un produit).
 *
 * Montants en minor units (entiers), statuts en string (enums cote PHP).
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_categories')) {
            Schema::create('restaurant_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('name', 120);
                $table->string('color', 7)->nullable();
                $table->smallInteger('sort_order')->default(0);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'branch_id', 'name'], 'restaurant_categories_company_branch_name_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_categories IS 'Categories de produits - nom unique par (tenant, branche), branch_id null = toutes branches (RESTO-202/#6167).';");
        }

        if (! schemaTableExists('restaurant_products')) {
            Schema::create('restaurant_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('category_id');
                $table->string('code', 40);
                $table->string('name', 150);
                $table->text('description_redacted')->nullable();
                $table->unsignedInteger('price_minor')->default(0);
                $table->char('currency', 3)->default('DZD');
                $table->unsignedInteger('cost_minor')->nullable();
                $table->unsignedBigInteger('tax_rate_id')->nullable();
                $table->boolean('is_available')->default(true);
                $table->unsignedBigInteger('image_asset_id')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_products_company_code_unique');
                $table->index(['company_id', 'category_id'], 'restaurant_products_company_category_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_products IS 'Produits a la carte - code unique par tenant, prix en minor units (RESTO-202/#6167).';");
        }

        if (! schemaTableExists('restaurant_product_ingredients')) {
            Schema::create('restaurant_product_ingredients', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 12, 3);
                $table->string('unit_code', 20);

                $table->timestamps();

                $table->unique(['company_id', 'product_id', 'ingredient_id'], 'restaurant_product_ingredients_company_product_ingredient_unique');
                $table->index(['company_id', 'product_id'], 'restaurant_product_ingredients_company_product_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_product_ingredients IS 'Fiche technique : composants d un produit - unique par (tenant, produit, ingredient) (RESTO-202/#6167).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_product_ingredients');
        Schema::dropIfExists('restaurant_products');
        Schema::dropIfExists('restaurant_categories');
    }
};

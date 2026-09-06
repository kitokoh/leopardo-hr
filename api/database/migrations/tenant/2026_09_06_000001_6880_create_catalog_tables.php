<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6880 (BC-28 CATALOG) - Catalogue produits B2B : categories + produits.
 *
 * - `catalog_categories` : categories (name, slug unique par tenant,
 *   `parent_id` nullable — hierarchie plate v1, sans FK) ;
 * - `catalog_products` : produits (prix indicatif `price_minor` en minor
 *   units + devise ISO 4217, unite libre bornee, statut `draft|published`,
 *   `meta` JSON libre — attributs/specs).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern RestaurantManager #6167). Idempotente +
 * down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 120);
                $table->string('slug', 130);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->smallInteger('position')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'catalog_categories_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE catalog_categories IS 'Categories du catalogue B2B - slug unique par tenant, hierarchie plate v1 (BC-28/#6880).';");
        }

        if (! schemaTableExists('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name', 150);
                $table->string('slug', 160);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price_minor')->default(0);
                $table->char('currency', 3)->default('XOF');
                $table->string('unit', 20)->default('piece');
                $table->string('status', 20)->default('draft');
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'catalog_products_company_slug_unique');
                $table->index(['company_id', 'category_id'], 'catalog_products_company_category_idx');
            });

            DB::statement("COMMENT ON TABLE catalog_products IS 'Produits du catalogue B2B - prix indicatif en minor units + devise ISO, statut draft|published (BC-28/#6880).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_categories');
    }
};

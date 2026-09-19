<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7672 (BC-17 RETAIL) - Fondations du module vendeur generique : categories + produits.
 *
 * - `retail_categories` : categories (name, slug unique par tenant,
 *   `parent_id` nullable — hierarchie plate v1, sans FK) ;
 * - `retail_products` : produits (prix de vente `price_minor` et cout
 *   d'achat `cost_minor` en minor units + devise ISO 4217, SKU unique par
 *   tenant, code-barres optionnel, statut `draft|published|archived`,
 *   `meta` JSON libre — attributs/specs).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern Catalog #6880). Idempotente +
 * down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_categories')) {
            Schema::create('retail_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 160);
                $table->string('slug', 180);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedSmallInteger('position')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'retail_categories_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE retail_categories IS 'Categories du module Retail - slug unique par tenant, hierarchie plate v1 (BC-17/#7672).';");
        }

        if (! schemaTableExists('retail_products')) {
            Schema::create('retail_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name', 200);
                $table->string('slug', 220);
                $table->string('sku', 64);
                $table->string('barcode', 64)->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price_minor')->default(0);
                $table->unsignedBigInteger('cost_minor')->nullable();
                $table->char('currency', 3)->default('XOF');
                $table->string('unit', 30)->nullable();
                $table->string('status', 20)->default('draft');
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'retail_products_company_slug_unique');
                $table->unique(['company_id', 'sku'], 'retail_products_company_sku_unique');
                $table->index(['company_id', 'status'], 'retail_products_company_status_idx');
                $table->index(['company_id', 'barcode'], 'retail_products_company_barcode_idx');
            });

            DB::statement("COMMENT ON TABLE retail_products IS 'Produits du module Retail - prix/cout en minor units + devise ISO, SKU unique par tenant, statut draft|published|archived (BC-17/#7672).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_products')) {
            Schema::dropIfExists('retail_products');
        }

        if (schemaTableExists('retail_categories')) {
            Schema::dropIfExists('retail_categories');
        }
    }
};

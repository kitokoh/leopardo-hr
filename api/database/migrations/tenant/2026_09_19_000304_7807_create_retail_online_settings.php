<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7807 (BC-17 RETAIL) - Marketplace publique : opt-in boutique en ligne +
 * visibilite en ligne des produits.
 *
 * - `retail_online_settings` : UNE ligne par tenant (opt-in explicite,
 *   defaut fail-closed enabled=false). Le `slug` est unique GLOBALEMENT
 *   (cross-tenant) : c'est l'identifiant public de la boutique dans
 *   l'annuaire `/public/market/sellers/{slug}` — jamais le company_id ;
 * - `retail_products.online_visible` : opt-in PAR PRODUIT (defaut false) —
 *   la visibilite publique exige produit `published` ET `online_visible`
 *   ET boutique `enabled` (spec MARKETPLACE_RETAIL_PUBLIC §2) ;
 * - `retail_products.image_url` : visuel public du produit (URL, optionnel).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern retail_* #7672/#7674).
 * Idempotente + down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_online_settings')) {
            Schema::create('retail_online_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('slug', 160);
                $table->string('display_name', 160);
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(false);

                $table->timestamps();

                $table->unique(['company_id'], 'retail_online_settings_company_unique');
                // Unicite GLOBALE volontaire (identifiant public cross-tenant
                // de la boutique — pattern restaurant_branches.public_slug #7746).
                $table->unique(['slug'], 'retail_online_settings_slug_unique');
                $table->index(['enabled'], 'retail_online_settings_enabled_idx');
            });

            DB::statement("COMMENT ON TABLE retail_online_settings IS 'Opt-in boutique en ligne du module Retail - une ligne par tenant, slug public unique GLOBAL, enabled=false par defaut (fail-closed) (BC-17/#7807).';");
        }

        if (schemaTableExists('retail_products') && ! schemaHasColumn('retail_products', 'online_visible')) {
            Schema::table('retail_products', function (Blueprint $table): void {
                $table->boolean('online_visible')->default(false);
            });

            Schema::table('retail_products', function (Blueprint $table): void {
                $table->index(['company_id', 'online_visible'], 'retail_products_company_online_visible_idx');
            });
        }

        if (schemaTableExists('retail_products') && ! schemaHasColumn('retail_products', 'image_url')) {
            Schema::table('retail_products', function (Blueprint $table): void {
                $table->string('image_url', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_products') && schemaHasColumn('retail_products', 'image_url')) {
            Schema::table('retail_products', function (Blueprint $table): void {
                $table->dropColumn('image_url');
            });
        }

        if (schemaTableExists('retail_products') && schemaHasColumn('retail_products', 'online_visible')) {
            Schema::table('retail_products', function (Blueprint $table): void {
                $table->dropIndex('retail_products_company_online_visible_idx');
                $table->dropColumn('online_visible');
            });
        }

        if (schemaTableExists('retail_online_settings')) {
            Schema::dropIfExists('retail_online_settings');
        }
    }
};

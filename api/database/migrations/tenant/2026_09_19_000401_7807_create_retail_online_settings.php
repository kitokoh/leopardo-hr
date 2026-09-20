<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7807 (BC-17 RETAIL, epic Leopardo Marche) - Vitrine marketplace publique :
 * reglages boutique en ligne + publication produit opt-in.
 *
 * - `retail_online_settings` : reglages de la boutique en ligne d'un tenant
 *   (1 ligne max par company — `company_id` UNIQUE). `enabled` = opt-in
 *   marketplace (defaut false, fail-closed), nom/description/ville publics,
 *   contacts optionnels, devise d'affichage ISO 4217 (defaut DZD), `version`
 *   pour verrou optimiste ;
 * - `retail_products` : ajout `online_visible` (opt-in publication
 *   marketplace, defaut false, indexe par tenant) et `image_url` (visuel
 *   public, URL absolue) — idempotent via Schema::hasColumn.
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern Retail #7672). Idempotente + down()
 * complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_online_settings')) {
            Schema::create('retail_online_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                $table->boolean('enabled')->default(false);
                $table->string('shop_name', 160);
                $table->text('shop_description')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->string('contact_email', 160)->nullable();
                $table->char('currency', 3)->default('DZD');
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->unique(['company_id'], 'retail_online_settings_company_unique');
                $table->index(['enabled'], 'retail_online_settings_enabled_idx');
            });

            DB::statement("COMMENT ON TABLE retail_online_settings IS 'Reglages boutique en ligne Leopardo Marche - 1 ligne par tenant, opt-in enabled fail-closed, devise ISO (BC-17/#7807).';");
        }

        if (schemaTableExists('retail_products')) {
            Schema::table('retail_products', function (Blueprint $table): void {
                if (! Schema::hasColumn('retail_products', 'online_visible')) {
                    $table->boolean('online_visible')->default(false);
                }

                if (! Schema::hasColumn('retail_products', 'image_url')) {
                    $table->string('image_url', 500)->nullable();
                }
            });

            // Index de la recherche marketplace (produits publies ET visibles
            // en ligne d'un tenant) — nomme, cree hors Blueprint pour rester
            // idempotent au rejeu partiel.
            DB::statement('CREATE INDEX IF NOT EXISTS retail_products_company_online_visible_idx ON retail_products (company_id, online_visible)');
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_products')) {
            DB::statement('DROP INDEX IF EXISTS retail_products_company_online_visible_idx');

            Schema::table('retail_products', function (Blueprint $table): void {
                if (Schema::hasColumn('retail_products', 'image_url')) {
                    $table->dropColumn('image_url');
                }

                if (Schema::hasColumn('retail_products', 'online_visible')) {
                    $table->dropColumn('online_visible');
                }
            });
        }

        if (schemaTableExists('retail_online_settings')) {
            Schema::dropIfExists('retail_online_settings');
        }
    }
};

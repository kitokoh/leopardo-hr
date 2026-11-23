<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9846 (BC-14 INTEGRATION) — connexions d'un TENANT.
 *
 * Une ligne = un compte externe qu'un client branche sur SON espace : son site
 * WordPress/WooCommerce, sa page produit Facebook, sa boutique Shopify, sa
 * boutique Instagram, son compte TikTok Shop, sa fiche Google Merchant…
 *
 * C'est le besoin « côté tenant : s'il le souhaite, un tenant doit pouvoir
 * connecter son site, sa page produit Facebook, ou d'autres plateformes
 * connues, notamment s'il a des produits ».
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, AUCUNE FK vers
 * `public.companies` (conventions migrations tenant §2.6) ; le modèle
 * `ConnectorConnection` porte `BelongsToCompany` (scope + fail-closed #3727).
 *
 * SÉCURITÉ : `secrets` chiffré au repos (cast `encrypted:array`) — clé API
 * WooCommerce, jeton de page Facebook, token Shopify. `config` ne porte que du
 * non-secret affichable (URL du site, identifiant de page, nom de la boutique).
 *
 * Migration réentrante et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('connector_connections')) {
            return;
        }

        Schema::create('connector_connections', function (Blueprint $table): void {
            $table->id();

            $table->uuid('company_id')->index();

            // Référence `connector_catalog.key` (catalogue global, schéma public).
            $table->string('catalog_key', 60)->index();

            $table->string('label', 120);

            // draft | active | error | revoked — même vocabulaire que côté
            // plateforme, pour que l'UI partage ses badges.
            $table->string('status', 20)->default('draft')->index();

            // Non-secret affichable (site_url, page_id, shop_domain, masques…).
            $table->jsonb('config')->nullable();

            // Chiffré au repos (cast `encrypted:array`).
            $table->text('secrets')->nullable();

            $table->string('last_check_status', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            // Requête de l'écran : « les connexions de ce tenant, par famille ».
            $table->index(['company_id', 'catalog_key', 'status'], 'connector_connections_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_connections');
    }
};

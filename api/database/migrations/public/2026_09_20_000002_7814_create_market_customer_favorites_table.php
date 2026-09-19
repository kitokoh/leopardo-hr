<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 — Favoris des acheteurs de Leopardo Marché (produits et boutiques).
 *
 * Table PLATEFORME (schéma public, PAS de company_id — dérogation justifiée) :
 * le favori appartient à l'ACHETEUR (compte plateforme #7814), pas à un
 * tenant — il référence les produits/boutiques PAR VALEUR (`product_id`
 * global, `seller_slug` public), jamais par FK cross-schéma (constitution
 * §II). La résolution publique (produit encore visible, boutique encore
 * opt-in) est faite à la lecture par RetailMarketplaceService (fail-closed :
 * un favori dont la cible n'est plus publique n'expose rien).
 *
 * Unicité : un favori par cible et par compte — index uniques PARTIELS
 * Postgres (les colonnes cibles sont mutuellement exclusives et nullables,
 * un unique composite classique ne bloque pas les doublons avec NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_customer_favorites')) {
            return;
        }

        Schema::create('market_customer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_account_id');
            // Cible : exactement l'une des deux colonnes (validé serveur).
            $table->string('target_type', 10); // product | seller
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('seller_slug', 120)->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'target_type']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS market_customer_favorites_product_unique '
            .'ON market_customer_favorites (customer_account_id, product_id) '
            ."WHERE target_type = 'product'"
        );
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS market_customer_favorites_seller_unique '
            .'ON market_customer_favorites (customer_account_id, seller_slug) '
            ."WHERE target_type = 'seller'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('market_customer_favorites');
    }
};

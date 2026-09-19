<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 — Avis & notations MODÉRÉS de Leopardo Marché (produit et boutique).
 *
 * Table PLATEFORME (schéma public — dérogation justifiée) : l'avis est écrit
 * par un ACHETEUR plateforme (#7814) et lu cross-tenant par la vitrine
 * publique. `company_id` est porté PAR VALEUR (colonne nue uuid, sans FK —
 * constitution §II) : il borne la MODÉRATION au vendeur concerné (un vendeur
 * ne modère que les avis de SA boutique/SES produits).
 *
 * Anti-abus (spec §6) :
 * - avis VÉRIFIÉ post-livraison : `order_id` (par valeur) prouve une commande
 *   LIVRÉE du compte chez ce vendeur (contenant le produit pour un avis
 *   produit) — vérifié serveur à la création ;
 * - modération : `status = pending` à la création, seuls les avis `approved`
 *   sont publics (fail-closed), `rejected` terminal ;
 * - un avis par cible et par compte (unicité applicative, la cible produit
 *   ou boutique étant mutuellement exclusive).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_reviews')) {
            return;
        }

        Schema::create('market_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_account_id');
            // Vendeur concerné, PAR VALEUR (modération bornée au tenant).
            $table->uuid('company_id');
            $table->string('target_type', 10); // product | seller
            $table->unsignedBigInteger('product_id')->nullable();
            // Commande livrée qui fonde l'avis (preuve d'achat, par valeur).
            $table->unsignedBigInteger('order_id');
            $table->unsignedTinyInteger('rating'); // 1..5, validé serveur
            $table->text('comment')->nullable();
            $table->string('status', 10)->default('pending'); // pending|approved|rejected
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id']);
            $table->index(['company_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS market_reviews_product_unique '
            .'ON market_reviews (customer_account_id, product_id) '
            ."WHERE target_type = 'product'"
        );
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS market_reviews_seller_unique '
            .'ON market_reviews (customer_account_id, company_id) '
            ."WHERE target_type = 'seller'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('market_reviews');
    }
};

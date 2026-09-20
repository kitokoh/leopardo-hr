<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 (BC-17 RETAIL, epic Leopardo Marche) — Avis verifies des acheteurs
 * marketplace (note 1..5 + commentaire).
 *
 * Table CENTRALE (schema public) : un avis rattache un acheteur PLATEFORME
 * a un produit d'un vendeur (company_id interne, jamais expose) et a la
 * commande qui prouve l'achat. Un avis n'est autorise que si la commande
 * du buyer contient le produit ET est `delivered` (avis verifie
 * post-livraison) — regle appliquee cote service.
 *
 * Anti-abus : UNIQUE (buyer_id, order_id, product_id) — 1 avis par produit
 * par commande. Statut de moderation `pending|approved|rejected`
 * (auto-approve v1, champ present pour la moderation v2).
 *
 * Sans FK, idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_reviews')) {
            return;
        }

        Schema::create('marketplace_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->uuid('company_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('approved');
            $table->timestamps();

            $table->unique(['buyer_id', 'order_id', 'product_id'], 'marketplace_reviews_buyer_order_product_unique');
            $table->index(['product_id', 'status'], 'marketplace_reviews_product_status_idx');
            $table->index(['company_id', 'status'], 'marketplace_reviews_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reviews');
    }
};

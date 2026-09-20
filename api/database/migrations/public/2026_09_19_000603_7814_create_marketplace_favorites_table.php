<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 (BC-17 RETAIL, epic Leopardo Marche) — Favoris des acheteurs
 * marketplace.
 *
 * Table CENTRALE (schema public) : le favori pointe un produit public de
 * la marketplace via (company_id, product_id) — le `company_id` du vendeur
 * est une donnee INTERNE (jamais exposee dans les DTO publics, l'API
 * publique identifie le produit par son seul id numerique) mais il est
 * stocke ici pour borner les lectures cross-tenant aux vendeurs opt-in.
 *
 * Un favori unique par (buyer, produit). Sans FK, idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_favorites')) {
            return;
        }

        Schema::create('marketplace_favorites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->uuid('company_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->unique(['buyer_id', 'company_id', 'product_id'], 'marketplace_favorites_buyer_product_unique');
            $table->index(['buyer_id'], 'marketplace_favorites_buyer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_favorites');
    }
};

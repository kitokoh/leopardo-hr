<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 — Comptes acheteurs GRAND PUBLIC de Leopardo Marché (BC-17 RETAIL,
 * backlog post-v1 de la spec MARKETPLACE_RETAIL_PUBLIC.md §6).
 *
 * Table PLATEFORME (schéma public, PAS de company_id — dérogation justifiée) :
 * un acheteur de la marketplace n'appartient à AUCUN tenant — il commande
 * chez plusieurs boutiques. Même pattern pré-tenant que
 * `travel_customer_accounts` (#7739), `marketing_leads` (PA2-MKT-007) et
 * `acquisition_funnel_events` (#7496). Le rattachement aux commandes se fait
 * côté tenant via `retail_orders.customer_account_id` (colonne nue, sans FK
 * cross-schéma — constitution §II).
 *
 * `email_verified_at` : posé plus tard par la vérification par lien (lot
 * ultérieur) ; à la création, le compte revendique les commandes en ligne
 * existantes portant son e-mail (rattachement « à la création », #7814).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_customer_accounts')) {
            return;
        }

        Schema::create('market_customer_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 255)->unique();
            $table->string('phone', 40)->nullable();
            // Hash bcrypt/argon (Hash::make) — jamais de mot de passe en clair.
            $table->string('password', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_customer_accounts');
    }
};

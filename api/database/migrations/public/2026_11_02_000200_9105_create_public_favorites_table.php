<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3 (#9105) · Favoris du client public (schéma `public`).
 *
 * ## Pourquoi une table neuve et pas `marketplace_favorites`
 *
 * `marketplace_favorites` existe déjà, mais elle appartient à la marketplace
 * retail : guard distinct, périmètre retail, et surtout elle référence des
 * entités d'UN tenant. Un client de la passerelle publique met en favori des
 * profils de PLUSIEURS verticales et de PLUSIEURS tenants — il n'a aucun
 * `company_id` à fournir, et le lui demander ouvrirait la porte à un favori
 * pointant vers un tenant arbitraire.
 *
 * Le favori stocke donc `(target_type, target_slug)` : le slug EST
 * l'identifiant public (même contrat que `/public/discover` et que le contact
 * public). Le tenant n'est jamais fourni par le client ; il est résolu côté
 * serveur au moment de l'affichage, fail-closed.
 *
 * ## Sous-domaine « profil public »
 *
 * Cette table vit dans le schéma `public`, jamais dans un schéma tenant : les
 * favoris appartiennent au CLIENT, pas au commerçant. Un commerçant ne doit
 * jamais pouvoir lire qui l'a mis en favori — c'est une donnée de la personne,
 * pas une donnée de l'entreprise.
 *
 * Idempotente : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.public_favorites')) {
            return;
        }

        Schema::create('public.public_favorites', function (Blueprint $table): void {
            $table->id();

            // Client public (users.id, guard `user_api`).
            $table->unsignedBigInteger('user_id');

            // Type canonique du profil (`restaurant`, `retail`, …) et slug
            // public. Aucun `company_id` : le client ne le connaît pas et ne
            // doit pas pouvoir le fournir.
            $table->string('target_type', 40);
            $table->string('target_slug', 160);

            $table->timestampsTz();

            // Un client ne met un profil en favori qu'UNE fois : c'est la clé
            // d'idempotence de `POST /user/favorites`.
            $table->unique(
                ['user_id', 'target_type', 'target_slug'],
                'public_favorites_user_target_unique',
            );

            // Lecture « mes favoris », du plus récent au plus ancien.
            $table->index(['user_id', 'created_at'], 'public_favorites_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.public_favorites');
    }
};

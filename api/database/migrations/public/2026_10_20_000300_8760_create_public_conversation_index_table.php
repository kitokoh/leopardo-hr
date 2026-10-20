<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8760 — Index PLATEFORME des conversations publiques.
 *
 * Un client de la passerelle converse avec PLUSIEURS entreprises, dont les
 * messages vivent dans des schémas tenants distincts (isolation stricte). Pour
 * lister « mes conversations », il faut donc savoir QUELS schémas interroger
 * avant d'ouvrir un contexte tenant — et il est hors de question de parcourir
 * `pg_namespace` depuis le code applicatif.
 *
 * Cette table ne contient AUCUN contenu de message : c'est une table de
 * ROUTAGE (client → tenant + fil), volontairement minimale :
 *
 *   - `user_id`  : le client (`users.id`, guard `user_api`) ;
 *   - `company_id` : le tenant qui héberge le fil ;
 *   - `thread_id` : l'identifiant du fil DANS le schéma du tenant ;
 *   - `target_slug` : le profil public visé (retrouver le fil existant).
 *
 * ⚠️ Elle ne remplace pas les contrôles d'accès : chaque lecture de fil rejoue
 * `belongsToPublicClient()` DANS le contexte tenant. Cette table ne fait que
 * dire où chercher ; elle n'autorise jamais une lecture à elle seule.
 *
 * Même patron que `public_contact_requests` (#8760) : les données d'un visiteur
 * sans tenant vivent dans le schéma public, jamais dans un schéma tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_conversation_index')) {
            return;
        }

        Schema::create('public_conversation_index', function (Blueprint $table): void {
            $table->id();

            // Client public (users.id) et tenant hôte du fil.
            $table->unsignedBigInteger('user_id');
            $table->uuid('company_id');

            // Fil DANS le schéma du tenant + profil public visé.
            $table->unsignedBigInteger('thread_id');
            $table->string('target_slug', 160);

            $table->timestampsTz();

            // Un client n'a qu'UN fil par (tenant, profil) : c'est la clé
            // d'idempotence de `startConversation()`.
            $table->unique(['user_id', 'company_id', 'target_slug'], 'public_conversation_index_user_tenant_slug_unique');

            // Lecture « mes conversations » (tri par récence côté application).
            $table->index(['user_id', 'updated_at'], 'public_conversation_index_user_updated_index');
            $table->index(['company_id', 'thread_id'], 'public_conversation_index_company_thread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_conversation_index');
    }
};

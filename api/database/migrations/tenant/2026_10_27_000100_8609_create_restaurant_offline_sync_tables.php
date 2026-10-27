<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant OS — REST-OS-7 Mode hors ligne (BC-25 RESTAURANT, #8609).
 *
 * PRINCIPE : le perimetre hors ligne du restaurant est DECLARE, operation par
 * operation, et rien d'autre n'est accepte. Un terminal de salle ne doit jamais
 * pouvoir confirmer silencieusement une operation que le serveur ne sait pas
 * reconcilier.
 *
 * Ce que le schema garantit :
 *   - `restaurant_sync_operations` est une FILE D'OPERATIONS LOCALES, chacune
 *     portant un `client_operation_id` UNIQUE par entreprise : le rejeu d'une
 *     operation deja recue est detecte et NE re-applique rien (idempotence) ;
 *   - `operation_type` est un registre FERME cote application : une operation
 *     hors ligne non prevue est refusee, jamais appliquee « au mieux » ;
 *   - `terminal_id` identifie le POSTE emetteur. C'est lui qui permet de
 *     detecter que deux terminaux deconnectes ont confirme des operations
 *     INCOMPATIBLES sur la meme entite (deux clotures de la meme table, deux
 *     paiements concurrents) : la seconde n'ecrase pas la premiere, elle part
 *     en conflit ;
 *   - `status` distingue `pending` / `applied` / `conflict` / `rejected` /
 *     `failed` : l'HISTORIQUE DES ERREURS est conserve, une operation en echec
 *     n'est jamais silencieusement perdue ;
 *   - `restaurant_sync_conflicts` conserve la resolution EXPLICITE d'un conflit
 *     (valeur locale, valeur serveur, strategie retenue, qui a tranche) : aucun
 *     ecrasement silencieux ;
 *   - `payload_hash` permet de detecter qu'une meme operation a ete poussee avec
 *     un contenu DIFFERENT (conflit d'identifiant, pas un simple rejeu).
 *
 * COHERENCE FINANCIERE ET STOCK : les encaissements et les mouvements de stock
 * ne sont PAS resolus en « dernier ecrit gagne ». Le service les marque en
 * conflit des qu'une divergence est detectee, pour arbitrage humain — c'est la
 * seule facon de ne pas fausser une recette ou un stock.
 *
 * Conventions respectees (docs/architecture + lecons CI) :
 *   - `company_id` uuid NULLABLE + indexe sur CHAQUE table (isolation tenant) ;
 *   - references internes par identifiant stable SANS cle etrangere ;
 *   - `timestampTz` partout ;
 *   - `up()` idempotent (`schemaTableExists`) — Render rejoue les migrations ;
 *   - `down()` ne detruit QUE ce que ce fichier a cree (garde #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_sync_operations')) {
            Schema::create('restaurant_sync_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Identifiant genere par le TERMINAL : c'est LUI qui rend le
                // rejeu idempotent, pas un identifiant serveur.
                $table->string('client_operation_id', 100);
                // Registre FERME cote application (voir RestaurantOfflineOperationType).
                $table->string('operation_type', 60);
                // Empreinte du contenu : detecte un meme identifiant pousse avec
                // un contenu different (conflit, pas un rejeu).
                $table->string('payload_hash', 64);
                $table->json('payload');

                // pending | applied | conflict | rejected | failed
                $table->string('status', 16)->default('pending');
                // Nombre de tentatives : une operation en echec reste visible.
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->text('last_error')->nullable();
                // Identifiant de l'enregistrement cree cote serveur, une fois
                // l'operation appliquee (permet au terminal de reconcilier).
                $table->string('applied_entity_type', 60)->nullable();
                $table->unsignedBigInteger('applied_entity_id')->nullable();

                // POSTE emetteur : indispensable pour detecter deux terminaux
                // deconnectes ayant confirme des operations incompatibles.
                $table->string('terminal_id', 100)->nullable();
                // Entite visee par l'operation (table, commande, session...).
                // Denormalisee ici pour que la detection de conflit
                // inter-terminaux soit une simple requete indexee.
                $table->string('target_entity_type', 60)->nullable();
                $table->string('target_entity_id', 100)->nullable();

                $table->unsignedBigInteger('submitted_by')->nullable();
                // Horodatage du terminal (peut etre anterieur a la reception).
                $table->timestampTz('occurred_at');
                $table->timestampTz('applied_at')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                // IDEMPOTENCE : un identifiant d'operation est unique par tenant.
                $table->unique(['company_id', 'client_operation_id'], 'restaurant_sync_operations_client_unique');
                $table->index(['company_id', 'status'], 'restaurant_sync_operations_status_index');
                $table->index(['company_id', 'operation_type'], 'restaurant_sync_operations_type_index');
                // Detection de conflit inter-terminaux : (entite visee, statut).
                $table->index(
                    ['company_id', 'target_entity_type', 'target_entity_id', 'status'],
                    'restaurant_sync_operations_target_index'
                );
            });
        }

        if (! schemaTableExists('restaurant_sync_conflicts')) {
            Schema::create('restaurant_sync_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('restaurant_sync_operation_id');
                $table->string('entity_type', 60);
                $table->string('entity_id', 100)->nullable();
                // Registre FERME cote application (voir RestaurantConflictStrategy).
                $table->string('strategy', 40);
                // Valeurs en presence : conservees pour audit et arbitrage.
                $table->json('local_value')->nullable();
                $table->json('server_value')->nullable();
                // Terminaux en presence : rend le conflit explicable a l'ecran.
                $table->string('local_terminal_id', 100)->nullable();
                $table->string('server_terminal_id', 100)->nullable();
                // Resolution retenue + qui a tranche (null = automatique).
                $table->string('resolution', 40)->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->text('note')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->index(['company_id', 'restaurant_sync_operation_id'], 'restaurant_sync_conflicts_operation_index');
                $table->index(['company_id', 'resolution'], 'restaurant_sync_conflicts_resolution_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les conflits referencent les operations.
        Schema::dropIfExists('restaurant_sync_conflicts');
        Schema::dropIfExists('restaurant_sync_operations');
    }
};

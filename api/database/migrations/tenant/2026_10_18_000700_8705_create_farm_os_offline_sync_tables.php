<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T5.2 Mode hors ligne (BC-37 AGRICULTURE, #8705).
 *
 * PRINCIPE : Farm OS ne promet JAMAIS une fonction hors ligne non implementee.
 * Ce que le terrain peut faire hors reseau est DECLARE ici, operation par
 * operation, et rien d'autre n'est accepte.
 *
 * Ce que le schema garantit :
 *   - `farm_sync_operations` est une FILE D'OPERATIONS LOCALES, chacune portant
 *     un `client_operation_id` UNIQUE par entreprise : le rejeu d'une operation
 *     deja appliquee est detecte et NE re-applique rien (idempotence) ;
 *   - `operation_type` est un registre FERME cote application : une operation
 *     hors ligne non prevue est refusee, jamais appliquee « au mieux » ;
 *   - `status` distingue `pending` / `applied` / `conflict` / `rejected` /
 *     `failed` : l'HISTORIQUE DES ERREURS est conserve, une operation en echec
 *     n'est jamais silencieusement perdue ;
 *   - `farm_sync_conflicts` conserve la resolution EXPLICITE d'un conflit
 *     (valeur locale, valeur serveur, strategie retenue, qui a tranche) : aucun
 *     ecrasement silencieux ;
 *   - `payload_hash` permet de detecter qu'une meme operation a ete poussee avec
 *     un contenu DIFFERENT (conflit d'identifiant, pas un simple rejeu).
 *
 * COHERENCE FINANCIERE ET STOCK : les operations financieres et les mouvements
 * de stock ne sont PAS resolues en « dernier ecrit gagne ». Le service les
 * marque en conflit des qu'une divergence est detectee, pour arbitrage humain —
 * c'est la seule facon de ne pas fausser une marge ou un stock.
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
        if (! schemaTableExists('farm_sync_operations')) {
            Schema::create('farm_sync_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Identifiant genere par l'appareil : c'est LUI qui rend le
                // rejeu idempotent, pas un identifiant serveur.
                $table->string('client_operation_id', 100);
                // Registre FERME cote application (voir FarmOfflineOperationType).
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
                // l'operation appliquee (permet a l'appareil de reconcilier).
                $table->string('applied_entity_type', 60)->nullable();
                $table->unsignedBigInteger('applied_entity_id')->nullable();

                $table->unsignedBigInteger('device_id')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                // Horodatage de l'appareil (peut etre anterieur a la reception).
                $table->timestampTz('occurred_at');
                $table->timestampTz('applied_at')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                // IDEMPOTENCE : un identifiant d'operation est unique par tenant.
                $table->unique(['company_id', 'client_operation_id'], 'farm_sync_operations_client_unique');
                $table->index(['company_id', 'status'], 'farm_sync_operations_status_index');
                $table->index(['company_id', 'operation_type'], 'farm_sync_operations_type_index');
            });
        }

        if (! schemaTableExists('farm_sync_conflicts')) {
            Schema::create('farm_sync_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_sync_operation_id');
                $table->string('entity_type', 60);
                $table->unsignedBigInteger('entity_id')->nullable();
                // Registre FERME cote application (voir FarmConflictStrategy).
                $table->string('strategy', 40);
                // Valeurs en presence : conservees pour audit et arbitrage.
                $table->json('local_value')->nullable();
                $table->json('server_value')->nullable();
                // Resolution retenue + qui a tranche (null = automatique).
                $table->string('resolution', 40)->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->text('note')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->index(['company_id', 'farm_sync_operation_id'], 'farm_sync_conflicts_operation_index');
                $table->index(['company_id', 'resolution'], 'farm_sync_conflicts_resolution_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les conflits referencent les operations.
        Schema::dropIfExists('farm_sync_conflicts');
        Schema::dropIfExists('farm_sync_operations');
    }
};

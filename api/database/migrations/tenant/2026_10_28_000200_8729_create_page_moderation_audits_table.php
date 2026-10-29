<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8729 (BC PAGES) — journal de modération APPEND-ONLY.
 *
 * ## Pourquoi une table dédiée et pas `audit_logs`
 *
 * `audit_logs` est un journal applicatif générique : rien n'empêche un
 * `UPDATE` ou un `DELETE` (aucun trigger, aucun `REVOKE`). Or l'exigence de
 * #8729 est explicite — « chaque décision de vérification/suspension exige
 * permission et motif puis écrit un **audit immuable** ». Un journal qu'un
 * opérateur peut réécrire ne prouve rien : c'est précisément la trace qu'un
 * contestataire voudrait effacer.
 *
 * Cette table porte donc son immuabilité **dans le schéma** : un trigger
 * PostgreSQL rejette tout `UPDATE` et tout `DELETE`. Ce n'est pas une
 * convention de code qu'un futur contrôleur pourrait contourner, c'est le
 * moteur qui refuse.
 *
 * ## Pourquoi pas de `updated_at`
 *
 * Une ligne immuable n'a pas d'état « après ». `created_at` seul rend la
 * tentative d'écriture d'un `updated_at` impossible par construction.
 *
 * ## Ce que la table ne contient PAS
 *
 * Aucun contenu de pièce justificative, aucun code SMS, aucune donnée
 * personnelle au-delà de l'identifiant de l'acteur plateforme. Le journal
 * prouve **qui a décidé quoi, quand et pourquoi** — pas ce qu'il a lu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('page_moderation_audits')) {
            Schema::create('page_moderation_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedBigInteger('page_id')->index();

                // Acteur plateforme : identifiant stable, jamais un nom libre.
                $table->string('actor_id', 120);
                $table->string('actor_role', 40)->nullable();

                // Verbe normalisé : `page.suspended`, `page.verification.approved`…
                $table->string('action', 80);

                // Motif OBLIGATOIRE : une décision sans motif n'est pas auditable.
                $table->string('reason', 80);

                // État avant/après, sans contenu métier sensible.
                $table->jsonb('before')->nullable();
                $table->jsonb('after')->nullable();

                $table->string('request_id', 80)->nullable();
                $table->string('ip_address', 45)->nullable();

                $table->timestampTz('created_at')->useCurrent();

                $table->index(['page_id', 'created_at'], 'page_moderation_audits_page_time_index');
                $table->index(['action', 'created_at'], 'page_moderation_audits_action_time_index');
            });
        }

        // Immuabilité portée par le moteur. `DROP TRIGGER IF EXISTS` d'abord :
        // la migration doit rester rejouable (règle AGENTS.md #8207).
        //
        // La fonction est créée AVANT le trigger : un trigger qui référence une
        // fonction absente échoue à la création, et l'erreur ne dirait pas
        // pourquoi. `CREATE OR REPLACE` la rend rejouable.
        DB::statement(
            'CREATE OR REPLACE FUNCTION page_moderation_audits_reject_mutation() '
            .'RETURNS trigger AS $$ '
            .'BEGIN '
            ."RAISE EXCEPTION 'page_moderation_audits is append-only: % refused', TG_OP; "
            .'END; '
            .'$$ LANGUAGE plpgsql'
        );

        DB::statement('DROP TRIGGER IF EXISTS page_moderation_audits_append_only ON page_moderation_audits');
        DB::statement(
            'CREATE TRIGGER page_moderation_audits_append_only '
            .'BEFORE UPDATE OR DELETE ON page_moderation_audits '
            .'FOR EACH ROW EXECUTE FUNCTION page_moderation_audits_reject_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS page_moderation_audits_append_only ON page_moderation_audits');
        DB::statement('DROP FUNCTION IF EXISTS page_moderation_audits_reject_mutation()');

        Schema::dropIfExists('page_moderation_audits');
    }
};

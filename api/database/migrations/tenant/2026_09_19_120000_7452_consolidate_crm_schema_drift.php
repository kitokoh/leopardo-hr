<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7452 — Consolidation du schéma CRM (dérive migrations ↔ modèles/tests).
 *
 * Même famille que la consolidation Travel (`2026_09_15_001600_7452`) : le
 * module CRM a été développé par plusieurs générations parallèles (fondation
 * V0 #5708/#5709/#5710 « maigre » mergée, batch V1 #5719-#5722 écrit contre le
 * schéma canonique « riche » convenu dans la fixture `CreatesCrmSchema`).
 * La migration mergée gagne, la fixture devient no-op (`schemaTableExists`),
 * et tout le code/les tests écrits contre la génération riche échouent en
 * `column "legal_name" of relation "crm_accounts" does not exist` (28×),
 * `column "lead_id" of relation "crm_tasks" does not exist` (12×),
 * `null value in column "stages" of relation "crm_pipelines"` (14×), puis
 * cascade `25P02` — mesuré : 104 échecs sur 236 dans `tests/Feature/CRM`.
 *
 * Correctif **forward-only**, idempotent (rejouable sur base migrée comme sur
 * base fraîche — cf. AGENTS.md « Render et migrations PostgreSQL ») :
 *
 *  1. les colonnes attendues par modèles/queries/tests et absentes du schéma
 *     réel sont ajoutées `nullable` (`Schema::table` gardé par
 *     `schemaHasColumn()`) — aucun écrivain existant n'est cassé ;
 *  2. `crm_pipelines.stages` (JSON embarqué de la génération V0) perd son
 *     `NOT NULL` : la génération vivante modélise les étapes dans
 *     `crm_pipeline_stages` (modèle `CrmPipelineStage`, read model
 *     `CrmDashboardReadModel`) et n'écrit jamais `stages` ;
 *  3. les colonnes de liaison de `crm_opportunities` typées `uuid` par la
 *     migration du swarm alors que TOUTES les PK cibles sont bigint
 *     (`crm_pipelines.id`, `crm_leads.id`, `employees.id`) sont re-typées
 *     `bigint` — l'incompatibilité est documentée dans `ConvertLeadAction`
 *     (« elles restent NULL ici pour éviter toute erreur de typage ») : aucune
 *     valeur exploitable ne peut y exister, la conversion mappe les valeurs
 *     non numériques sur NULL. Idem `crm_automations.created_by` (uuid) qui
 *     reçoit des id d'employés bigint (22P02 `invalid input syntax for type
 *     uuid: "39"`).
 *
 * @see https://github.com/kitokoh/leopardo-hr/issues/7452
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── crm_accounts : colonnes de la génération riche (#5708 / PR #5757) ──
        $this->addColumn('crm_accounts', 'legal_name', static fn (Blueprint $t) => $t->string('legal_name', 191)->nullable());
        $this->addColumn('crm_accounts', 'industry', static fn (Blueprint $t) => $t->string('industry', 100)->nullable());
        $this->addColumn('crm_accounts', 'website', static fn (Blueprint $t) => $t->string('website', 255)->nullable());
        $this->addColumn('crm_accounts', 'address', static fn (Blueprint $t) => $t->string('address', 255)->nullable());
        $this->addColumn('crm_accounts', 'city', static fn (Blueprint $t) => $t->string('city', 100)->nullable());
        $this->addColumn('crm_accounts', 'country', static fn (Blueprint $t) => $t->char('country', 2)->nullable());
        $this->addColumn('crm_accounts', 'tax_id', static fn (Blueprint $t) => $t->string('tax_id', 50)->nullable());
        $this->addColumn('crm_accounts', 'source', static fn (Blueprint $t) => $t->string('source', 20)->nullable()->default('manual'));
        $this->addColumn('crm_accounts', 'metadata', static fn (Blueprint $t) => $t->jsonb('metadata')->nullable());

        // ── crm_contacts : job_title (backfill depuis title) + metadata ──────
        $this->addColumn('crm_contacts', 'job_title', static fn (Blueprint $t) => $t->string('job_title', 100)->nullable());
        $this->backfillFrom('crm_contacts', 'job_title', 'title');
        $this->addColumn('crm_contacts', 'metadata', static fn (Blueprint $t) => $t->jsonb('metadata')->nullable());

        // ── crm_tasks : rattachements et cycle de vie de la génération riche ─
        $this->addColumn('crm_tasks', 'lead_id', static fn (Blueprint $t) => $t->unsignedBigInteger('lead_id')->nullable()->index());
        $this->addColumn('crm_tasks', 'opportunity_id', static fn (Blueprint $t) => $t->unsignedBigInteger('opportunity_id')->nullable()->index());
        $this->addColumn('crm_tasks', 'completed_at', static fn (Blueprint $t) => $t->timestampTz('completed_at')->nullable());
        $this->addColumn('crm_tasks', 'assigned_to', static fn (Blueprint $t) => $t->unsignedBigInteger('assigned_to')->nullable());
        $this->addColumn('crm_tasks', 'completed_by', static fn (Blueprint $t) => $t->unsignedBigInteger('completed_by')->nullable());
        $this->addColumn('crm_tasks', 'created_by', static fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── crm_pipelines : la génération vivante modélise les étapes dans
        //    crm_pipeline_stages ; `stages` (JSON V0) n'est jamais renseignée. ─
        $this->relaxNotNull('crm_pipelines', ['stages']);
        $this->addColumn('crm_pipelines', 'description', static fn (Blueprint $t) => $t->text('description')->nullable());
        $this->addColumn('crm_pipelines', 'created_by', static fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── crm_opportunities : liaisons bigint + colonnes de la génération
        //    riche (stage_id → crm_pipeline_stages, read model #5722). ────────
        $this->retypeUuidToBigint('crm_opportunities', ['pipeline_id', 'lead_id', 'owner_id']);
        $this->addColumn('crm_opportunities', 'stage_id', static fn (Blueprint $t) => $t->unsignedBigInteger('stage_id')->nullable()->index());
        $this->addColumn('crm_opportunities', 'account_id', static fn (Blueprint $t) => $t->unsignedBigInteger('account_id')->nullable()->index());
        $this->addColumn('crm_opportunities', 'converted_from_lead_id', static fn (Blueprint $t) => $t->unsignedBigInteger('converted_from_lead_id')->nullable());
        $this->addColumn('crm_opportunities', 'source', static fn (Blueprint $t) => $t->string('source', 40)->nullable());
        $this->addColumn('crm_opportunities', 'description', static fn (Blueprint $t) => $t->text('description')->nullable());
        $this->addColumn('crm_opportunities', 'won_at', static fn (Blueprint $t) => $t->timestampTz('won_at')->nullable());
        $this->addColumn('crm_opportunities', 'lost_at', static fn (Blueprint $t) => $t->timestampTz('lost_at')->nullable());
        $this->addColumn('crm_opportunities', 'created_by', static fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── crm_automations : created_by reçoit des id d'employés (bigint). ──
        $this->retypeUuidToBigint('crm_automations', ['created_by']);

        // ── crm_leads / crm_export_jobs : mêmes liaisons uuid inécrivables. ──
        $this->retypeUuidToBigint('crm_leads', ['account_id', 'owner_id']);
        $this->retypeUuidToBigint('crm_export_jobs', ['user_id']);
    }

    public function down(): void
    {
        // Forward-only : retirer des colonnes potentiellement peuplées serait
        // destructif, et re-typer bigint → uuid ne peut pas être fait sans
        // perte. Le `down()` est volontairement un no-op (même politique que
        // 2026_09_15_000001_7452_relax_legacy_travel_not_null).
    }

    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (! schemaTableExists($table) || schemaHasColumn($table, $column)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    /**
     * Retire le NOT NULL d'une colonne « zombie » de la génération gagnante :
     * le code courant ne la renseigne jamais, tout INSERT échouerait en 23502.
     * La colonne est conservée (aucune donnée perdue). Idempotent (un second
     * DROP NOT NULL est un no-op PostgreSQL).
     *
     * @param  list<string>  $columns
     */
    private function relaxNotNull(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL', $table, $column));
        }
    }

    /**
     * Recopie une colonne historique dans la colonne canonique attendue par le
     * code, pour les lignes déjà écrites (base migrée avant ce correctif).
     */
    private function backfillFrom(string $table, string $target, string $source): void
    {
        if (! schemaTableExists($table) || ! schemaHasColumn($table, $target) || ! schemaHasColumn($table, $source)) {
            return;
        }

        DB::statement(sprintf('UPDATE %s SET %s = %s WHERE %s IS NULL', $table, $target, $source, $target));
    }

    /**
     * Re-type une colonne uuid en bigint. Les cibles réelles de ces liaisons
     * ont des PK bigint : aucune valeur uuid n'a jamais pu être écrite par un
     * écrivain valide (les INSERT échouaient en 22P02, cf. ConvertLeadAction
     * qui laisse volontairement ces colonnes NULL). Une éventuelle valeur non
     * numérique est mappée sur NULL (USING CASE), la conversion est donc sûre
     * et idempotente (gardée par le type courant).
     *
     * @param  list<string>  $columns
     */
    private function retypeUuidToBigint(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                continue;
            }

            $type = DB::selectOne(
                'SELECT data_type FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, $column],
            );

            if ($type === null || $type->data_type !== 'uuid') {
                continue; // déjà bigint (base fraîche post-correctif) : no-op.
            }

            DB::statement(sprintf(
                'ALTER TABLE %1$s ALTER COLUMN %2$s TYPE bigint USING (CASE WHEN %2$s::text ~ \'^[0-9]+$\' THEN %2$s::text::bigint ELSE NULL END)',
                $table,
                $column,
            ));
        }
    }
};

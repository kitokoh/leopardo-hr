<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7572 — Consolidation du schéma Fuel (tranche de #7452).
 *
 * Les 5 tables Fuel étaient **toutes divergentes** : chacune déclarée par 2 ou
 * 3 migrations (`Schema::create` en double, gardées par `schemaTableExists()`
 * ou par l'existence de la table), la **première en ordre d'exécution gagne**,
 * les suivantes sont des no-op **silencieux**. Le code et les tests écrits
 * contre la dernière génération travaillent donc sur des colonnes absentes
 * (`column "x" does not exist`, souvent masqué par une cascade `25P02`),
 * et à l'inverse les colonnes `NOT NULL` de la génération gagnante que ce code
 * ne renseigne **jamais** font échouer chaque INSERT en `23502`.
 *
 * Mesure de départ (`dev-hub/tools/check-duplicate-schema-create.py --audit`) :
 * 5 tables divergentes, **20 colonnes absentes** du schéma réel.
 *
 * Correctif **forward-only** (aucune réécriture d'historique, aucun second
 * `Schema::create`) :
 *
 *  1. les colonnes attendues par la génération perdante sont ajoutées par
 *     `Schema::table`, gardées par `schemaHasColumn()` ;
 *  2. les colonnes « zombies » de la génération gagnante que le code ne
 *     renseigne jamais perdent leur `NOT NULL` : sans cela tout INSERT échoue
 *     en `23502`. Elles sont conservées (aucune donnée perdue) ;
 *  3. là où les deux générations portent la même information sous deux noms
 *     (`occurred_at`/`reported_at`, `description`/`description_redacted`,
 *     `scheduled_for`/`due_at`), la colonne attendue est **recopiée** depuis
 *     l'ancienne pour les lignes déjà écrites ;
 *  4. les index d'unicité attendus par le code (`(company_id, external_id)`)
 *     sont posés, avec repli journalisé sur un index simple si la base est
 *     déjà peuplée de doublons.
 *
 * Les déclarations concurrentes sont supprimées dans le même commit (une table
 * = une déclaration), ce qui résorbe la duplication au sens de la garde.
 *
 * Idempotente : rejouable sur une base migrée (Render rejoue des migrations)
 * comme sur une base fraîche. `down()` retire uniquement ce que `up()` a ajouté.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Incidents (#5804) ──────────────────────────────────────────────
        $this->addColumn('fuel_incidents', 'category', static fn (Blueprint $t) => $t->string('category', 40)->default('other'));
        $this->addColumn('fuel_incidents', 'description_redacted', static fn (Blueprint $t) => $t->text('description_redacted')->nullable());
        $this->backfillFrom('fuel_incidents', 'description_redacted', 'description');
        $this->addColumn('fuel_incidents', 'reported_at', static fn (Blueprint $t) => $t->timestampTz('reported_at')->nullable());
        $this->backfillFrom('fuel_incidents', 'reported_at', 'occurred_at');
        $this->addColumn('fuel_incidents', 'assigned_at', static fn (Blueprint $t) => $t->timestampTz('assigned_at')->nullable());
        $this->addColumn('fuel_incidents', 'attachments_metadata', static fn (Blueprint $t) => $t->jsonb('attachments_metadata')->nullable());
        $this->addColumn('fuel_incidents', 'external_id', static fn (Blueprint $t) => $t->string('external_id', 120)->nullable());
        // La génération perdante ne connaît pas `title` : tout INSERT échouerait en 23502.
        $this->relaxNotNull('fuel_incidents', ['title']);
        $this->addUnique('fuel_incidents', ['company_id', 'external_id'], 'fuel_incidents_ext_unique');

        // ── Tâches de maintenance (#5804) ──────────────────────────────────
        $this->addColumn('fuel_maintenance_tasks', 'created_by', static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable());
        $this->addColumn('fuel_maintenance_tasks', 'description_redacted', static fn (Blueprint $t) => $t->text('description_redacted')->nullable());
        $this->backfillFrom('fuel_maintenance_tasks', 'description_redacted', 'description');
        $this->addColumn('fuel_maintenance_tasks', 'due_at', static fn (Blueprint $t) => $t->timestampTz('due_at')->nullable());
        $this->backfillFrom('fuel_maintenance_tasks', 'due_at', 'scheduled_for');
        $this->addColumn('fuel_maintenance_tasks', 'started_at', static fn (Blueprint $t) => $t->timestampTz('started_at')->nullable());
        $this->addColumn('fuel_maintenance_tasks', 'external_id', static fn (Blueprint $t) => $t->string('external_id', 120)->nullable());
        $this->relaxNotNull('fuel_maintenance_tasks', ['title']);
        $this->addUnique('fuel_maintenance_tasks', ['company_id', 'external_id'], 'fuel_maintenance_tasks_ext_unique');

        // ── Réconciliation de stock (#5803) ────────────────────────────────
        $this->addColumn('fuel_reconciliation_runs', 'created_at', static fn (Blueprint $t) => $t->timestampTz('created_at')->nullable());
        $this->addColumn('fuel_reconciliation_runs', 'updated_at', static fn (Blueprint $t) => $t->timestampTz('updated_at')->nullable());

        // ── Snapshots de rapports (#5811) ──────────────────────────────────
        $this->addColumn('fuel_report_snapshots', 'snapshot_type', static fn (Blueprint $t) => $t->string('snapshot_type', 40)->nullable());
        $this->addColumn('fuel_report_snapshots', 'period_start', static fn (Blueprint $t) => $t->date('period_start')->nullable());
        $this->addColumn('fuel_report_snapshots', 'period_end', static fn (Blueprint $t) => $t->date('period_end')->nullable());
        $this->addColumn('fuel_report_snapshots', 'generated_by', static fn (Blueprint $t) => $t->unsignedInteger('generated_by')->nullable());
        $this->addColumn('fuel_report_snapshots', 'generated_at', static fn (Blueprint $t) => $t->timestampTz('generated_at')->nullable());
        $this->addColumn('fuel_report_snapshots', 'created_at', static fn (Blueprint $t) => $t->timestampTz('created_at')->nullable());
        $this->addColumn('fuel_report_snapshots', 'updated_at', static fn (Blueprint $t) => $t->timestampTz('updated_at')->nullable());
        // `report_type`/`snapshot_date`/`payload`/`computed_at` n'existent que dans
        // la génération gagnante : la seconde écrit `snapshot_type`/`period_*`.
        $this->relaxNotNull('fuel_report_snapshots', ['report_type', 'snapshot_date', 'payload', 'computed_at']);
    }

    public function down(): void
    {
        $this->dropColumns('fuel_incidents', ['category', 'description_redacted', 'reported_at', 'assigned_at', 'attachments_metadata', 'external_id']);
        $this->dropColumns('fuel_maintenance_tasks', ['created_by', 'description_redacted', 'due_at', 'started_at', 'external_id']);
        $this->dropColumns('fuel_reconciliation_runs', ['created_at', 'updated_at']);
        $this->dropColumns('fuel_report_snapshots', ['snapshot_type', 'period_start', 'period_end', 'generated_by', 'generated_at', 'created_at', 'updated_at']);
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
     * Retire le `NOT NULL` d'une colonne « zombie » de la génération gagnante :
     * le code ne la renseigne jamais, l'INSERT échouerait en 23502. La colonne
     * est conservée (aucune donnée perdue).
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
     * @param  list<string>  $columns
     */
    private function addUnique(string $table, array $columns, string $name): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        // Colonnes manquantes : l'index serait inconstructible, on passe.
        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($name)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $present = array_values(array_filter($columns, static fn (string $column): bool => schemaHasColumn($table, $column)));

        if ($present === []) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($present): void {
            $blueprint->dropColumn($present);
        });
    }

    private function indexExists(string $name): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$name]) !== null;
    }
};

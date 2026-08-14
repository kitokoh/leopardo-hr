<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-PAIE (issue #1813) — workflow de validation des modifications de taux
 * légaux : double signature + audit trail immuable.
 *
 * 1. Colonnes additives sur `tax_slabs` et `social_contributions` :
 *    - `status`           : draft | pending_validation | active | superseded
 *                           (défaut 'active' → rétrocompatibilité totale,
 *                           les lignes existantes restent actives) ;
 *    - `submitted_by`     : acteur RH/comptable qui soumet ;
 *    - `validated_by`     : platform_admin qui approuve ;
 *    - `validated_at`     : date d'approbation ;
 *    - `rejection_reason` : motif de rejet (obligatoire au reject).
 *    Seules les lignes `status = 'active'` sont utilisées par les calculs
 *    (AbstractCountryRules::resolveTaxSlabsFromDatabase() / resolveContribution()).
 *
 * 2. Table `tax_rate_change_log` (append-only) : historique immuable de
 *    toutes les transitions. Un trigger PostgreSQL REFUSE tout UPDATE/DELETE
 *    (l'immutabilité est garantie au niveau base, pas seulement applicatif).
 *
 * Migration additive et idempotente (pattern schema-aware du module Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addValidationColumns('tax_slabs');
        $this->addValidationColumns('social_contributions');
        $this->createChangeLog();
    }

    private function addValidationColumns(string $table): void
    {
        $schema = resolveTableSchema($table);

        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.{$table}", function (Blueprint $blueprint) use ($table): void {
            if (! schemaHasColumn($table, 'status')) {
                $blueprint->string('status', 30)->default('active')->index();
            }
            if (! schemaHasColumn($table, 'submitted_by')) {
                $blueprint->unsignedBigInteger('submitted_by')->nullable();
            }
            if (! schemaHasColumn($table, 'validated_by')) {
                $blueprint->unsignedBigInteger('validated_by')->nullable();
            }
            if (! schemaHasColumn($table, 'validated_at')) {
                $blueprint->timestampTz('validated_at')->nullable();
            }
            if (! schemaHasColumn($table, 'rejection_reason')) {
                $blueprint->text('rejection_reason')->nullable();
            }
        });
    }

    private function createChangeLog(): void
    {
        // Le log vit dans le schéma tenant courant (comme les tables de paie).
        $schema = resolveTableSchema('tax_slabs');

        if ($schema === null) {
            return;
        }

        if (! Schema::hasTable('tax_rate_change_log')) {
            Schema::create('tax_rate_change_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('table_name', 50);
                $table->unsignedBigInteger('record_id');
                $table->string('action', 30);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_role', 30);
                $table->jsonb('previous_value')->nullable();
                $table->jsonb('new_value');
                $table->text('reason')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['table_name', 'record_id']);
            });
        }

        // Immutabilité base : UPDATE/DELETE refusés sur la table append-only.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION tax_rate_change_log_prevent_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'tax_rate_change_log is append-only: UPDATE/DELETE are forbidden (issue #1813)';
            END;
            $$
        SQL);

        DB::statement("DROP TRIGGER IF EXISTS tax_rate_change_log_no_mutation ON {$schema}.tax_rate_change_log");
        DB::statement(<<<SQL
            CREATE TRIGGER tax_rate_change_log_no_mutation
            BEFORE UPDATE OR DELETE ON {$schema}.tax_rate_change_log
            FOR EACH ROW EXECUTE FUNCTION tax_rate_change_log_prevent_mutation()
        SQL);
    }

    public function down(): void
    {
        $schema = resolveTableSchema('tax_slabs');

        if ($schema !== null) {
            DB::statement("DROP TRIGGER IF EXISTS tax_rate_change_log_no_mutation ON {$schema}.tax_rate_change_log");
            Schema::dropIfExists('tax_rate_change_log');
        }

        foreach (['tax_slabs', 'social_contributions'] as $table) {
            $tableSchema = resolveTableSchema($table);
            if ($tableSchema === null) {
                continue;
            }

            Schema::table("{$tableSchema}.{$table}", function (Blueprint $blueprint) use ($table): void {
                foreach (['status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason'] as $column) {
                    if (schemaHasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};

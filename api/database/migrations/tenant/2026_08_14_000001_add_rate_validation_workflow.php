<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1813 — Workflow de validation des modifications de taux légaux.
 *
 * Les tables `tax_slabs` et `social_contributions` alimentent directement les
 * bulletins (AbstractCountryRules::resolveTaxSlabsFromDatabase /
 * resolveContributionRate) : une modification erronée affecterait toutes les
 * paies suivantes. Ce workflow protège ces tables :
 *
 * - `status` : draft → pending_validation → active | superseded (défaut
 *   'active' pour les lignes existantes : rétrocompatibilité totale) ;
 * - `tax_rate_change_log` : audit trail append-only (aucun UPDATE/DELETE
 *   possible au niveau base + modèle) ;
 * - seules les lignes `active` sont utilisées dans les calculs.
 */
return new class extends Migration
{
    private const TABLES = ['tax_slabs', 'social_contributions'];

    public function up(): void
    {
        // F-17 (#1595/#1933) : les gardes `Schema::hasTable()` nus ne voient
        // que `current_schema()` et diffèrent entre environnements (phpunit/CI :
        // search_path `public,shared_tenants` vs local/Render : `shared_tenants,public`)
        // — une table peut exister dans un autre schéma du search_path et la
        // migration être silencieusement sautée. Tous les accès sont résolus via
        // `current_schemas(false)` et QUALIFIÉS par le schéma réel.
        $rateSchema = null;

        foreach (self::TABLES as $table) {
            $rateSchema = resolveTableSchema($table);

            if ($rateSchema === null) {
                continue; // Table absente dans ce contexte — rien à migrer.
            }

            $qualified = $rateSchema.'.'.$table;

            // Revue lead (#1933) : gardes PAR COLONNE — un re-run (retry Render,
            // rattrapage entrée d'API) ne doit pas relancer ADD COLUMN sur des
            // colonnes déjà présentes (SQLSTATE 42701 duplicate_column).
            if (! schemaHasColumn($table, 'status')) {
                Schema::table($qualified, function (Blueprint $blueprint): void {
                    $blueprint->string('status', 20)->default('active')->index();
                });
            }

            if (! schemaHasColumn($table, 'submitted_by')) {
                Schema::table($qualified, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('submitted_by')->nullable();
                });
            }

            if (! schemaHasColumn($table, 'validated_by')) {
                Schema::table($qualified, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('validated_by')->nullable();
                });
            }

            if (! schemaHasColumn($table, 'validated_at')) {
                Schema::table($qualified, function (Blueprint $blueprint): void {
                    $blueprint->timestamp('validated_at')->nullable();
                });
            }

            if (! schemaHasColumn($table, 'rejection_reason')) {
                Schema::table($qualified, function (Blueprint $blueprint): void {
                    $blueprint->text('rejection_reason')->nullable();
                });
            }

            // Rétrocompat : les lignes EXISTANTES restent 'active' (défaut posé
            // ci-dessus), mais toute NOUVELLE ligne créée sans statut explicite
            // doit passer par le workflow → défaut 'draft' désormais.
            // (`ALTER COLUMN ... SET DEFAULT` est idempotent.)
            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN status SET DEFAULT \'draft\'', $qualified));
        }

        // `tax_rate_change_log` : audit trail créé dans le MÊME schéma que les
        // tables de taux (résolu ci-dessus), nom qualifié (F-17).
        if ($rateSchema !== null && resolveTableSchema('tax_rate_change_log') === null) {
            $qualifiedLog = $rateSchema.'.tax_rate_change_log';

            Schema::create($qualifiedLog, function (Blueprint $table): void {
                $table->id();
                $table->string('table_name', 50); // 'tax_slabs' | 'social_contributions'
                $table->unsignedBigInteger('record_id');
                $table->string('action', 30); // created | submitted | approved | rejected | superseded
                $table->unsignedBigInteger('actor_id');
                $table->string('actor_role', 30);
                $table->jsonb('previous_value')->nullable();
                $table->jsonb('new_value');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['table_name', 'record_id']);
                $table->index('created_at');
            });

            // Append-only : aucun UPDATE/DELETE possible pour le rôle applicatif.
            DB::statement(sprintf('REVOKE UPDATE, DELETE ON TABLE %s FROM PUBLIC', $qualifiedLog));
        }
    }

    public function down(): void
    {
        $logSchema = resolveTableSchema('tax_rate_change_log');

        if ($logSchema !== null) {
            Schema::dropIfExists($logSchema.'.tax_rate_change_log');
        }

        foreach (self::TABLES as $table) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            // Gardes par colonne : un rollback partiel/re-run ne doit pas
            // tenter de dropper une colonne absente (42703).
            foreach (['status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason'] as $column) {
                if (! schemaHasColumn($table, $column)) {
                    continue;
                }

                Schema::table($schema.'.'.$table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1927 — immutabilité de `tax_rate_change_log` AU NIVEAU BASE.
 *
 * La migration `2026_08_14_000001_add_rate_validation_workflow` (#1813)
 * créait l'audit trail avec un REVOKE UPDATE/DELETE FROM PUBLIC et un
 * blocage au niveau modèle (TaxRateChangeLog), mais sans trigger
 * PostgreSQL : le propriétaire de la table (ou un futur GRANT) pouvait
 * toujours muter/supprimer des lignes d'audit directement en SQL.
 *
 * Ce trigger `BEFORE UPDATE OR DELETE` rend la table strictement
 * append-only au niveau moteur (P0001) — aligné sur la variante #1861.
 *
 * Pattern F-17 (#1595/#1933) : la table vit dans le schéma tenant réel
 * (résolu via `resolveTableSchema`), le garde est search_path-aware.
 */
return new class extends Migration
{
    private const TRIGGER_NAME = 'tax_rate_change_log_append_only_trigger';

    public function up(): void
    {
        $schema = resolveTableSchema('tax_rate_change_log');

        if ($schema === null) {
            return; // Table absente dans ce contexte — rien à protéger.
        }

        $qualified = $schema.'.tax_rate_change_log';

        // Fonction créée dans le schéma courant (search_path tenant).
        DB::statement(
            <<<'SQL'
            CREATE OR REPLACE FUNCTION tax_rate_change_log_append_only()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'tax_rate_change_log is append-only (issue #1927)';
            END;
            $$;
            SQL
        );

        DB::statement(sprintf(
            'DROP TRIGGER IF EXISTS %s ON %s',
            self::TRIGGER_NAME,
            $qualified,
        ));

        DB::statement(sprintf(
            'CREATE TRIGGER %s BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION tax_rate_change_log_append_only()',
            self::TRIGGER_NAME,
            $qualified,
        ));
    }

    public function down(): void
    {
        $schema = resolveTableSchema('tax_rate_change_log');

        if ($schema === null) {
            return;
        }

        DB::statement(sprintf(
            'DROP TRIGGER IF EXISTS %s ON %s',
            self::TRIGGER_NAME,
            $schema.'.tax_rate_change_log',
        ));
    }
};

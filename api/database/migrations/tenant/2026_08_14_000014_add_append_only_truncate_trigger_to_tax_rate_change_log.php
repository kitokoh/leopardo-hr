<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2024 — complément du trigger append-only : bloquer TRUNCATE.
 *
 * La migration `2026_08_14_000011_add_append_only_trigger_to_tax_rate_change_log`
 * (#1927) couvre UPDATE/DELETE mais pas TRUNCATE : le propriétaire de la
 * table (rôle applicatif en prod) pouvait toujours vider la piste d'audit
 * en un seul `TRUNCATE` — même gap que le REVOKE initial (#1813).
 *
 * Ce trigger `BEFORE TRUNCATE` lève la même exception (P0001) que
 * UPDATE/DELETE, rendant la table strictement append-only y compris pour
 * le vidage. Note : TRUNCATE n'est pas exécuté ligne à ligne, le trigger
 * `BEFORE TRUNCATE` est donc FOR EACH STATEMENT (FOR EACH ROW interdit).
 *
 * Pattern F-17 (#1595/#1933) : table dans le schéma tenant réel (résolu
 * via `resolveTableSchema`), garde search_path-aware.
 */
return new class extends Migration
{
    private const TRIGGER_NAME = 'tax_rate_change_log_append_only_truncate_trigger';

    public function up(): void
    {
        $schema = resolveTableSchema('tax_rate_change_log');

        if ($schema === null) {
            return; // Table absente dans ce contexte — rien à protéger.
        }

        $qualified = $schema.'.tax_rate_change_log';

        // Même fonction que #1927 : TRUNCATE déclenche la même exception.
        DB::statement(
            <<<'SQL'
            CREATE OR REPLACE FUNCTION tax_rate_change_log_append_only()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'tax_rate_change_log is append-only (issue #1927/#2024)';
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
            'CREATE TRIGGER %s BEFORE TRUNCATE ON %s FOR EACH STATEMENT EXECUTE FUNCTION tax_rate_change_log_append_only()',
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

<?php

use App\Core\Tenant\Domain\Models\Company;

if (! function_exists('currentCompany')) {
    /**
     * Retrieve the current tenant company from the container.
     */
    function currentCompany(): Company
    {
        /** @var Company $company */
        $company = app('current_company');

        return $company;
    }
}


if (! function_exists('resolveTableSchema')) {
    /**
     * Résout le schéma où `DB::table($table)` trouverait la table, en suivant
     * l'ORDRE du search_path de la session (convention issue #1613).
     *
     * Pourquoi : `Schema::hasTable('x')`/`Schema::table('x')` interrogent
     * UNIQUEMENT `current_schema()`, alors que `DB::table('x')` résout via le
     * search_path (public,shared_tenants selon le contexte CI/phpunit). Une
     * garde au nom nu peut donc répondre `false` à tort et faire sauter
     * silencieusement un backfill ou un ALTER (bug F-17, audit passe 5).
     *
     * @return string|null Nom du schéma contenant la table, ou null.
     */
    function resolveTableSchema(string $table): ?string
    {
        $row = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT t.table_schema
               FROM information_schema.tables t
              WHERE t.table_name = ?
                AND t.table_schema = ANY (current_schemas(false))
              ORDER BY array_position(current_schemas(false), t.table_schema)
              LIMIT 1",
            [$table]
        );

        return $row ? (string) $row->table_schema : null;
    }
}

if (! function_exists('schemaTableExists')) {
    /**
     * Garde d'existence résolue via le search_path (issue #1613).
     */
    function schemaTableExists(string $table): bool
    {
        return resolveTableSchema($table) !== null;
    }
}

if (! function_exists('schemaHasColumn')) {
    /**
     * Garde de colonne résolue via le search_path (issue #1613).
     */
    function schemaHasColumn(string $table, string $column): bool
    {
        $schema = resolveTableSchema($table);
        if ($schema === null) {
            return false;
        }

        $row = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT 1
               FROM information_schema.columns
              WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?",
            [$schema, $table, $column]
        );

        return $row !== null;
    }
}

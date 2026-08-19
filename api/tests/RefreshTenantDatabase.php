<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

trait RefreshTenantDatabase
{
    use RefreshDatabase;

    /**
     * Exécute `migrate:fresh` sur le schéma `public` SEULEMENT.
     *
     * Aligné sur le bootstrap CI (`.github/actions/setup-backend-db` qui lance
     * `DB_SEARCH_PATH=public php artisan migrate --path=...public`).
     *
     * Pourquoi la config (pas seulement `SET search_path`) est OBLIGATOIRE —
     * régression 2026-08-17, 100 % des tests Feature cassés sur main :
     * - `migrate:fresh` appelle `db:wipe`, qui DISCONNECTE la connexion
     *   (`WipeCommand::flushDatabaseConnection()`).
     * - La reconnexion applique le `search_path` de la CONFIG
     *   (`shared_tenants,public`), pas celui de la session.
     * - Le repository `migrations` est alors créé dans `shared_tenants`,
     *   alors que la plupart des migrations publiques font un
     *   `SET search_path TO public` explicite (audit #1663) : le premier
     *   `insert into "migrations"` après 0002 échoue en `relation
     *   "migrations" does not exist`.
     * - En forçant la config à `public` AVANT le `migrate:fresh`, le repo et
     *   les tables publiques atterrissent dans `public`, comme en CI.
     */
    private function runPublicMigrations(): void
    {
        $connection = $this->app['db']->connection();
        $name = $connection->getName();
        $originalSearchPath = config("database.connections.{$name}.search_path");

        if (DB::getDriverName() === 'pgsql') {
            config(["database.connections.{$name}.search_path" => 'public']);
            DB::purge($name);
            DB::reconnect($name);

            // Réinitialisation COMPLÈTE des deux schémas. Sans ce DROP, les
            // fichiers qui utilisent `CreatesMvpSchema` (AI*Test,
            // QaHardeningEndpointsTest, Platform*Test...) laissent un schéma
            // `shared_tenants` PARTIEL (fixture mvp + DROP SCHEMA du repo
            // migrations) : le `migrate:fresh` suivant ne droppe que les
            // tables publiques (db:wipe limité au search_path=public) et les
            // migrations tenant re-créent des tables déjà présentes
            // (`relation "projects" already exists`, 42P07) → toute la suite
            // Feature en aval échoue en cascade. Observé 2026-08-17.
            DB::statement('DROP SCHEMA IF EXISTS shared_tenants CASCADE');
            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
        }

        try {
            $this->artisan('migrate:fresh', [
                '--path' => 'database/migrations/public',
            ]);
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                config(["database.connections.{$name}.search_path" => $originalSearchPath]);
                DB::purge($name);
                DB::reconnect($name);
            }
        }
    }

    /**
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->runPublicMigrations();

        $this->runTenantMigrations();

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh the test database.
     */
    protected function refreshTestDatabase(): void
    {
        // Some MVP fixture tests rebuild schemas outside Laravel's migration
        // repository. The static flag can therefore remain stale even after a
        // teardown; verify the canonical tables before trusting it.
        if (DB::getDriverName() === 'pgsql' && ! $this->canonicalSchemaReady()) {
            RefreshDatabaseState::$migrated = false;
        }

        if (! RefreshDatabaseState::$migrated) {
            $this->runPublicMigrations();

            $this->runTenantMigrations();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    private function canonicalSchemaReady(): bool
    {
        $connection = DB::connection();
        $searchPath = (string) ($connection->getConfig('search_path') ?? '');

        if ($searchPath === '') {
            return false;
        }

        return $connection->getSchemaBuilder()->hasTable('companies')
            && $connection->getSchemaBuilder()->hasTable('export_history');
    }

    /**
     * Exécute les migrations tenant dans le schéma `shared_tenants`.
     *
     * Aligné sur le bootstrap CI (`.github/actions/setup-backend-db` qui lance
     * `DB_SEARCH_PATH=shared_tenants php artisan migrate --path=...tenant`) :
     * sans ce SET, les tables tenant atterrissent dans le premier schéma du
     * search_path de la connexion (`public,shared_tenants` en tests) alors que
     * l'application résout les tables via `current_schema()` → `shared_tenants`
     * (ex. `Schema::hasColumn` du listing employés).
     */
    private function runTenantMigrations(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Le schéma `shared_tenants` peut manquer sur une base fraîche
            // (ex. jobs payroll-ci.yml / coverage qui ne passent pas par
            // .github/actions/setup-backend-db) : le créer de façon idempotente
            // pour que `SET search_path TO shared_tenants` ne lève pas
            // SQLSTATE 3F000 (no schema has been selected to create in) au
            // moment de `create table "migrations"`.
            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            DB::statement('SET search_path TO shared_tenants');
        }

        $this->artisan('migrate', [
            '--path' => 'database/migrations/tenant',
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }
    }
}

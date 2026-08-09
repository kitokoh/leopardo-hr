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
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations/public',
        ]);

        $this->runTenantMigrations();

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh the test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', [
                '--path' => 'database/migrations/public',
            ]);

            $this->runTenantMigrations();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
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

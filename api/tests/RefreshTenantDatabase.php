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
        // Use explicit schema qualification. SchemaBuilder::hasTable() follows
        // the mutable session search_path, which can temporarily point at a
        // tenant fixture and make a healthy canonical database look missing;
        // that would remigrate before every test and exhaust Coverage timeout.
        $row = DB::selectOne(
            "SELECT to_regclass('public.companies') AS companies,\n                    to_regclass('shared_tenants.export_history') AS export_history,\n                    to_regclass('shared_tenants.onboarding_steps') AS onboarding_steps,\n                    to_regclass('shared_tenants.social_contributions') AS social_contributions,\n                    to_regclass('shared_tenants.employees') AS employees,\n                    to_regclass('shared_tenants.app_notifications') AS app_notifications"
        );

        return $row !== null
            && $row->companies !== null
            && $row->export_history !== null
            && $row->onboarding_steps !== null
            && $row->social_contributions !== null
            // Issue #5201 : une fixture MVP partielle (CreatesMvpSchema) peut
            // laisser un schéma tenant avec ces tables mais SANS `employees`
            // et/ou avec une `app_notifications` d'ancienne génération (create
            // inline #2395 : user_id unsignedInteger, pas d'action_url) — la
            // garde détecte l'état incomplet et force la re-migration (sinon :
            // « relation "employees" does not exist » / 25P02 dans le test
            // suivant du worker, observés en CI workers 3-4).
            && $row->employees !== null
            && $row->app_notifications !== null;
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
            // Restaurer le search_path CONFIGURÉ (CI/phpunit : public,shared_tenants)
            // au lieu de laisser la session sur shared_tenants,public : des tables
            // « ombres » du schéma tenant (training_enrollments, payments,
            // attendance_logs...) masqueraient les vraies tables public et
            // casseraient les requêtes Eloquent non qualifiées des modules public
            // (partners, commissions, public_holidays...) — régression 25P02/0 row
            // observée sur les tests de course (GrowthPartnerRaceTest #2999).
            $defaultPath = (string) config('database.connections.'.DB::connection()->getName().'.search_path', 'shared_tenants,public');
            DB::statement('SET search_path TO '.$defaultPath);
        }
    }
}

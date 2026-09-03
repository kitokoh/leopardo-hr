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

            // Issue #6754 — un schéma `public` pollué par une fixture partielle
            // ou un run interrompu fait échouer `migrate:fresh` au setup de
            // TOUS les fichiers suivants du process : `db:wipe` ne purge que
            // les tables résolues via le search_path courant et laisse des
            // types composites/enum orphelins → le `CREATE TABLE` suivant lève
            // 23505 (pg_type_typname_nsp_index, ex. `seed_locks`), observé
            // 2026-09-02 en exécution multi-fichiers (2 fichiers Feature → 100 %
            // d'erreurs). On purge donc explicitement tables ET types du schéma
            // `public` avant le `migrate:fresh`.
            $this->purgePublicSchema();
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
     * Purge les tables ET les types du schéma `public`.
     *
     * `db:wipe` (migrate:fresh) ne supprime que les tables résolues via le
     * search_path courant ; les types composites/enum orphelins d'un schéma
     * pollué survivent et font échouer le CREATE TABLE de la migration
     * homonyme (23505 pg_type_typname_nsp_index). Le DROP explicite table +
     * type rend la re-migration hermétique quel que soit l'état résiduel.
     *
     * @see https://github.com/kitokoh/leopardo-hr/issues/6754
     */
    private function purgePublicSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DO $do$
            DECLARE
                r record;
            BEGIN
                -- Tables ORDINAIRES et PARTITIONNÉES du schéma `public`
                -- (`pg_tables` ne liste pas les tables partitionnées,
                -- relkind 'p') : leurs row types composites disparaissent
                -- avec elles.
                FOR r IN
                    SELECT c.relname
                    FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public'
                      AND c.relkind IN ('r', 'p')
                LOOP
                    EXECUTE 'DROP TABLE IF EXISTS public.' || quote_ident(r.relname) || ' CASCADE';
                END LOOP;

                -- Types composites/enum/domain orphelins (plus aucune table
                -- `public` résidente ne les référence après la purge ci-dessus).
                -- Chaque DROP est isolé : un type encore référencé par une
                -- table conservée (ex. row type d'une table d'un autre schéma,
                -- ou relation non couverte ci-dessus) lève 2BP01
                -- (dependent_objects_still_exist) et ne doit PAS faire échouer
                -- toute la purge — `migrate:fresh` qui suit gère le reste.
                FOR r IN
                    SELECT t.typname
                    FROM pg_catalog.pg_type t
                    JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace
                    WHERE n.nspname = 'public'
                      AND t.typtype IN ('c', 'e', 'd')
                      AND t.typname NOT LIKE '\_%'
                LOOP
                    BEGIN
                        EXECUTE 'DROP TYPE IF EXISTS public.' || quote_ident(r.typname) || ' CASCADE';
                    EXCEPTION
                        WHEN dependent_objects_still_exist THEN
                            NULL; -- row type d'une table résiduelle : migrate:fresh la droppe
                    END;
                END LOOP;
            END $do$;
            SQL);
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
     * Dernière classe de test ayant rafraîchi la base dans CE process.
     *
     * Statique de trait : chaque classe utilisant le trait possède sa propre
     * copie (vérifié — sémantique PHP), donc toute nouvelle classe voit `null`
     * au premier test : on force `RefreshDatabaseState::$migrated = false`
     * pour rejouer une migration COMPLÈTE et propre, exactement comme un run
     * isolé. Sans ce reset, l'état statique du fichier précédent (même
     * process) peut être périmé (repo tenant sans tables, tables sans repo,
     * search_path de session dérivé…) — issue #6754.
     */
    private static ?string $lastRefreshingClass = null;

    /**
     * Refresh the test database.
     */
    protected function refreshTestDatabase(): void
    {
        // Herméticité multi-fichiers (issue #6754) : un NOUVEAU fichier de
        // tests dans le même process ne doit JAMAIS faire confiance à l'état
        // statique laissé par le fichier précédent. Le run séquentiel devient
        // ainsi équivalent au run isolé (migration fraîche par classe).
        if (self::$lastRefreshingClass !== static::class) {
            RefreshDatabaseState::$migrated = false;
            self::$lastRefreshingClass = static::class;
        }

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
            "SELECT to_regclass('public.companies') AS companies,\n                    to_regclass('shared_tenants.export_history') AS export_history,\n                    to_regclass('shared_tenants.onboarding_steps') AS onboarding_steps,\n                    to_regclass('shared_tenants.social_contributions') AS social_contributions,\n                    to_regclass('shared_tenants.employees') AS employees,\n                    to_regclass('shared_tenants.app_notifications') AS app_notifications,\n                    to_regclass('shared_tenants.migrations') AS migrations_repo,\n                    to_regclass('shared_tenants.training_enrollments') AS training_enrollments"
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
            && $row->app_notifications !== null
            // Issue #6754 : la fixture `CreatesMvpSchema` laisse un schéma
            // PARTIEL qui peut inclure les 6 tables canoniques ci-dessus (garde
            // #5201) MAIS sans repository `migrations` tenant ni les tables hors
            // canon (ex. training_*) : un RefreshTenantDatabase suivant
            // croirait la base prête et sauterait la re-migration → « relation
            // "training_enrollments" does not exist » / 25P02 en cascade. Le
            // repository de migrations est donc exigé : seule une migration
            // complète le crée.
            && $row->migrations_repo !== null
            // Issue #6754 : garde complémentaire — un repo `migrations` peut
            // exister (migrations jouées) alors que la table a disparu
            // (interruption entre DROP et re-migration) ; `training_enrollments`
            // est la table du crash « alter table ... add constraint ».
            && $row->training_enrollments !== null;
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
            // Herméticité (issue #6754) : DROP + CREATE du schéma tenant AVANT
            // la re-migration, symétrique de `runPublicMigrations`. Sans ce
            // DROP, un état résiduel (run interrompu, fixture partielle) fait
            // échouer `migrate --path=tenant` : tables orphelines sans repo →
            // `relation "projects" already exists` (42P07, repo recréé vide) ;
            // ou repo marquant `create_training_tables` comme joué alors que la
            // table a disparu → `relation "training_enrollments" does not
            // exist` pendant `alter table ... add constraint` (42P01) — le
            // crash exact signalé dans #6754.
            DB::statement('DROP SCHEMA IF EXISTS shared_tenants CASCADE');
            // Le schéma peut manquer sur une base fraîche
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

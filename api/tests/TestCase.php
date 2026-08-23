<?php

namespace Tests;

use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // La configuration de la connexion doit être appliquée AVANT que les
        // traits ne s'exécutent (RefreshDatabase → migrate:fresh +
        // beginDatabaseTransaction). L'ordre d'origine (`parent::setUp()` en
        // premier) laissait `configureTestingDatabaseConnection()` purger et
        // RECONNECTER la connexion APRÈS l'ouverture de la transaction du
        // trait : la transaction était silencieusement perdue (nouveau PDO
        // sans BEGIN) → chaque test re-migrait la base (~87 fichiers × ~1 min)
        // et les écritures fuyaient d'un test à l'autre (violations
        // d'unicité en cascade, ex. employees_email_unique — observé
        // 2026-08-17 sur main).
        // $this->app est typé non-nullable par Larastan — la garde reste utile à
        // l'exécution (premier setUp avant tout refresh), on neutralise le faux positif.
        // @phpstan-ignore-next-line booleanNot.alwaysFalse
        if (! $this->app) {
            $this->refreshApplication();
            ParallelTesting::callSetUpTestCaseCallbacks($this);
        }

        $this->configureTestingDatabaseConnection();

        parent::setUp();
        $this->ensurePersonalOnboardingColumns();
        $this->resetTestSearchPath();
    }

    protected function tearDown(): void
    {
        $this->resetTestSearchPath();
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function resetTestSearchPath(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }
    }

    private function configureTestingDatabaseConnection(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $defaultConnection = config('database.default', 'pgsql');
        $connection = is_string($defaultConnection) && $defaultConnection !== ''
            ? $defaultConnection
            : 'pgsql';
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'pgsql') {
            return;
        }

        $host = $this->envValueForTesting(
            'DB_HOST',
            $this->configString("database.connections.{$connection}.host", '127.0.0.1')
        );

        $database = $this->envValueForTesting(
            'DB_DATABASE',
            $this->configString("database.connections.{$connection}.database", 'leopardo_test')
        );

        if (ParallelTesting::token() !== false) {
            // CI (tests.yml, issue #4695) : en `--parallel`, chaque worker
            // paratest utilise SA propre base `{db}_test_{token}`. Laravel ne
            // bascule que pour les tests avec traits DB (RefreshDatabase & co) ;
            // les tests CreatesMvpSchema (schéma créé/détruit à chaque test)
            // resteraient sinon sur la base commune → collisions inter-processus
            // (relation already exists / does not exist). On bascule donc ici
            // pour TOUS les tests, en créant la base du worker si absente.
            $database = $database.'_test_'.ParallelTesting::token();
            $this->ensureParallelDatabaseExists($database);
        }

        if ($this->isRunningInsideDocker() && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            // Check for CI environment variables explicitly using getenv for robustness
            $isCI = getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true';

            if (! $isCI) {
                $host = 'pgsql';
            }
        }

        config([
            "database.connections.{$connection}.host" => $host,
            "database.connections.{$connection}.port" => $this->envValueForTesting('DB_PORT', $this->configString("database.connections.{$connection}.port", '5432')),
            "database.connections.{$connection}.database" => $database,
            "database.connections.{$connection}.username" => $this->envValueForTesting('DB_USERNAME', $this->configString("database.connections.{$connection}.username", 'leopardo_user')),
            "database.connections.{$connection}.password" => $this->envValueForTesting('DB_PASSWORD', $this->configString("database.connections.{$connection}.password", 'leopardo_pass_test')),
            "database.connections.{$connection}.search_path" => $this->envValueForTesting('DB_SEARCH_PATH', $this->configString("database.connections.{$connection}.search_path", 'shared_tenants,public')),
        ]);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    /**
     * Crée la base du worker parallèle si elle n'existe pas encore.
     *
     * `CREATE DATABASE` ne peut pas s'exécuter dans une transaction ; à ce
     * stade du setUp, seuls les tests RefreshDatabase ont ouvert une
     * transaction (et pour eux Laravel a DÉJÀ créé la base via le hook
     * TestDatabases avant le beginDatabaseTransaction) → on ne crée que si
     * aucune transaction n'est active.
     */
    /** @var array<string, true> */
    private static array $parallelPublicMigrated = [];

    private function ensureParallelDatabaseExists(string $database): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }

        $lockKey = 'leopardo_test_database_creation_'.$database;
        DB::select('SELECT pg_advisory_lock(hashtext(?))', [$lockKey]);

        try {
            try {
                Schema::createDatabase($database);
            } catch (QueryException $exception) {
                // 42P04 duplicate_database → base déjà créée par un test précédent.
                if (! str_contains($exception->getMessage(), '42P04')) {
                    throw $exception;
                }
            }
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtext(?))', [$lockKey]);
        }

        $connection = DB::getDefaultConnection();
        $originalDatabase = config("database.connections.{$connection}.database");

        // La base worker doit être sélectionnée AVANT les migrations. Sinon
        // plusieurs processus migrent la base commune `leopardo_test`, ce qui
        // produit à la fois des colonnes dupliquées et des workers incomplets.
        config(["database.connections.{$connection}.database" => $database]);
        DB::purge($connection);
        DB::reconnect($connection);

        try {
            $this->ensureWorkerPublicSchema($database);
        } finally {
            config(["database.connections.{$connection}.database" => $originalDatabase]);
            DB::purge($connection);
            DB::reconnect($connection);
        }
    }

    /**
     * Les bases de workers parallèles (`{db}_test_{token}`) sont créées vides
     * par l'infra de test : aucun test n'a le schéma `public` migré tant que le
     * premier test du worker n'est pas un RefreshTenantDatabase. Depuis que
     * `Company` est qualifié `public.companies` (fix prod #5198), tout test
     * touchant le modèle Company échoue en `relation "public.companies" does
     * not exist` si le worker n'a pas migré — échecs en cascade sur ~38 classes
     * (Payment, Onboarding, SSO, Payroll...).
     *
     * On bootstrap donc les migrations publiques UNE SEULE FOIS par base de
     * worker (même contrat que `.github/actions/setup-backend-db` : migrations
     * `public` + schéma `shared_tenants`), puis on restaure la connexion.
     */
    private function ensureWorkerPublicSchema(string $database): void
    {
        if (isset(self::$parallelPublicMigrated[$database])) {
            return;
        }

        $connection = DB::getDefaultConnection();
        $originalSearchPath = config("database.connections.{$connection}.search_path");

        config(["database.connections.{$connection}.search_path" => 'public']);
        DB::purge($connection);
        DB::reconnect($connection);

        // Plusieurs processus d’un même worker peuvent entrer ici en même temps.
        // Le tableau statique ne protège que le processus courant ; le verrou
        // advisory protège donc la création du schéma entre processus.
        $lockKey = 'leopardo_test_public_schema_'.$database;
        DB::select('SELECT pg_advisory_lock(hashtext(?))', [$lockKey]);

        try {
            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');

            $this->artisan('migrate', [
                '--path' => 'database/migrations/public',
                '--force' => true,
            ]);

            $this->ensurePersonalOnboardingColumns();
            self::$parallelPublicMigrated[$database] = true;
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtext(?))', [$lockKey]);
            config(["database.connections.{$connection}.search_path" => $originalSearchPath]);
            DB::purge($connection);
            DB::reconnect($connection);
        }
    }

    private function ensurePersonalOnboardingColumns(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $connection = DB::getDefaultConnection();
        $originalSearchPath = $this->configString(
            "database.connections.{$connection}.search_path",
            'shared_tenants,public'
        );

        DB::statement('SET search_path TO public');

        try {
            if (! Schema::hasTable('users')) {
                return;
            }

            $missing = [];

            foreach ([
                'personal_statuses' => static function (Blueprint $table): void {
                    $table->json('personal_statuses')->nullable();
                },
                'personal_onboarding_completed_at' => static function (Blueprint $table): void {
                    $table->timestamp('personal_onboarding_completed_at')->nullable();
                },
                'job_search_preferences' => static function (Blueprint $table): void {
                    $table->json('job_search_preferences')->nullable();
                },
                'job_search_profile_updated_at' => static function (Blueprint $table): void {
                    $table->timestamp('job_search_profile_updated_at')->nullable();
                },
            ] as $column => $definition) {
                if (! Schema::hasColumn('users', $column)) {
                    $missing[$column] = $definition;
                }
            }

            if ($missing === []) {
                return;
            }

            Schema::table('users', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        } finally {
            DB::statement('SET search_path TO '.$originalSearchPath);
        }
    }

    private function envValueForTesting(string $key, string $fallback): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        return $value;
    }

    private function configString(string $key, string $fallback): string
    {
        $value = config($key, $fallback);

        return is_string($value) && $value !== ''
            ? $value
            : $fallback;
    }

    private function isRunningInsideDocker(): bool
    {
        if (getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true') {
            return false;
        }

        return file_exists('/.dockerenv');
    }
}

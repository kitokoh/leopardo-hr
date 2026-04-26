#!/bin/sh
set -e

echo "Optimizing Laravel at startup..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

db_pdo_bootstrap() {
    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$dbUrl = getenv('DB_URL') ?: '';

if ($dbUrl) {
    $parts = parse_url($dbUrl);
    if ($parts !== false) {
        $host = $host ?: ($parts['host'] ?? '');
        $port = $port ?: (isset($parts['port']) ? (string) $parts['port'] : '');
        $database = $database ?: ltrim($parts['path'] ?? '', '/');
        $username = $username ?: ($parts['user'] ?? '');
        $password = $password ?: ($parts['pass'] ?? '');
    }
}

$host = $host ?: '127.0.0.1';
$port = $port ?: '5432';

if ($database === '') {
    fwrite(STDERR, "DB bootstrap error: missing DB_DATABASE/DB_URL database name.\n");
    exit(1);
}

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $ddl = <<<'SQL'
CREATE SCHEMA IF NOT EXISTS shared_tenants;
CREATE TABLE IF NOT EXISTS public.migrations (
    id serial PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch integer NOT NULL
);
CREATE TABLE IF NOT EXISTS shared_tenants.migrations (
    id serial PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch integer NOT NULL
);
CREATE TABLE IF NOT EXISTS public.seed_locks (
    lock_key varchar(255) PRIMARY KEY,
    ran_at timestamp with time zone null,
    created_at timestamp with time zone not null default now(),
    updated_at timestamp with time zone not null default now()
);
SQL;

    $pdo->exec($ddl);
    echo "Migration repositories ensured (public/shared_tenants).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "DB bootstrap error: {$e->getMessage()}\n");
    exit(1);
}
PHP
}

ensure_migration_repository() {
    db_pdo_bootstrap
}

wait_for_db_bootstrap() {
    attempt=1
    max_attempts=30

    while [ "$attempt" -le "$max_attempts" ]; do
        if ensure_migration_repository; then
            return 0
        fi

        echo "Database not ready for bootstrap ($attempt/$max_attempts), retry in 2s..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "Database bootstrap failed after $max_attempts attempts."
    return 1
}

run_migrate_with_retry() {
    path="$1"
    search_path="$2"
    attempt=1
    max_attempts=3

    while [ "$attempt" -le "$max_attempts" ]; do
        if DB_SEARCH_PATH="$search_path" php artisan migrate --path="$path" --force --isolated >/tmp/render-migrate.log 2>&1; then
            cat /tmp/render-migrate.log
            return 0
        fi

        cat /tmp/render-migrate.log

        if grep -Eq 'SQLSTATE\[42P07\]|relation "migrations" already exists' /tmp/render-migrate.log; then
            if [ "$attempt" -lt "$max_attempts" ]; then
                echo "Migrations table race detected for $path ($attempt/$max_attempts), retrying in 2s..."
                sleep 2
            else
                echo "Migrations table race persisted for $path after $max_attempts attempts."
                echo "Running isolated global catch-up migrations..."
                if DB_SEARCH_PATH="$search_path" php artisan migrate --path="$path" --force --isolated >/tmp/render-migrate-catchup.log 2>&1; then
                    cat /tmp/render-migrate-catchup.log
                    return 0
                fi
                cat /tmp/render-migrate-catchup.log
                return 1
            fi
        elif [ "$attempt" -lt "$max_attempts" ]; then
            echo "Migration failed for $path ($attempt/$max_attempts), retry in 5s..."
            sleep 5
        else
            echo "Final migration failure for $path."
            return 1
        fi

        attempt=$((attempt + 1))
    done

    return 1
}

maybe_reset_test_database_once() {
    reset_once=$(php -r "echo filter_var(getenv('RESET_TEST_DB_ONCE') ?: false, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';")

    if [ "$reset_once" != "true" ]; then
        return 1
    fi

    reset_key="${RESET_TEST_DB_LOCK_KEY:-render_test_db_reset_v1}"

    if php <<PHP
<?php
try {
    \$pdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1',
            getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432',
            getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/')
        ),
        getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '',
        getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$pdo->exec("CREATE TABLE IF NOT EXISTS public.seed_locks (lock_key varchar(255) PRIMARY KEY, ran_at timestamp with time zone null, created_at timestamp with time zone not null default now(), updated_at timestamp with time zone not null default now())");
    \$stmt = \$pdo->prepare('SELECT 1 FROM public.seed_locks WHERE lock_key = :lock_key LIMIT 1');
    \$stmt->execute(['lock_key' => '${reset_key}']);
    exit(\$stmt->fetchColumn() ? 0 : 1);
} catch (Throwable \$e) {
    fwrite(STDERR, "Reset check error: {\$e->getMessage()}\n");
    exit(2);
}
PHP
    then
        echo "One-shot test DB reset already applied for lock ${reset_key}, skipping destructive reset."
        return 1
    fi

    echo "RESET_TEST_DB_ONCE=true detected. Performing one-shot full reset of test database..."

    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432';
$database = getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/');
$username = getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '';
$password = getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$database}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->beginTransaction();
    $pdo->exec(<<<'SQL'
DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP TABLE IF EXISTS public.%I CASCADE', r.tablename);
    END LOOP;

    FOR r IN
        SELECT sequencename
        FROM pg_sequences
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP SEQUENCE IF EXISTS public.%I CASCADE', r.sequencename);
    END LOOP;

    FOR r IN
        SELECT matviewname
        FROM pg_matviews
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP MATERIALIZED VIEW IF EXISTS public.%I CASCADE', r.matviewname);
    END LOOP;

    FOR r IN
        SELECT viewname
        FROM pg_views
        WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP VIEW IF EXISTS public.%I CASCADE', r.viewname);
    END LOOP;
END $$;
DROP SCHEMA IF EXISTS shared_tenants CASCADE;
CREATE SCHEMA shared_tenants;
SQL);
    $pdo->commit();
    echo "Test database schemas reset completed.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Test database reset failed: {$e->getMessage()}\n");
    exit(1);
}
PHP

    touch /tmp/render-reset-db-once-ran
    return 0
}

mark_test_database_reset_complete() {
    if [ ! -f /tmp/render-reset-db-once-ran ]; then
        return 0
    fi

    reset_key="${RESET_TEST_DB_LOCK_KEY:-render_test_db_reset_v1}"

    php <<PHP
<?php
try {
    \$pdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_HOST) ?: '127.0.0.1',
            getenv('DB_PORT') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PORT) ?: '5432',
            getenv('DB_DATABASE') ?: ltrim(parse_url(getenv('DB_URL') ?: '', PHP_URL_PATH) ?: '', '/')
        ),
        getenv('DB_USERNAME') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_USER) ?: '',
        getenv('DB_PASSWORD') ?: parse_url(getenv('DB_URL') ?: '', PHP_URL_PASS) ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$pdo->exec("CREATE TABLE IF NOT EXISTS public.seed_locks (lock_key varchar(255) PRIMARY KEY, ran_at timestamp with time zone null, created_at timestamp with time zone not null default now(), updated_at timestamp with time zone not null default now())");
    \$stmt = \$pdo->prepare('
        INSERT INTO public.seed_locks (lock_key, ran_at, created_at, updated_at)
        VALUES (:lock_key, NOW(), NOW(), NOW())
        ON CONFLICT (lock_key) DO UPDATE SET ran_at = EXCLUDED.ran_at, updated_at = EXCLUDED.updated_at
    ');
    \$stmt->execute(['lock_key' => '${reset_key}']);
    echo "Recorded one-shot test DB reset lock ${reset_key}.\n";
} catch (Throwable \$e) {
    fwrite(STDERR, "Reset lock write error: {\$e->getMessage()}\n");
    exit(1);
}
PHP

    rm -f /tmp/render-reset-db-once-ran
}

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Ensuring migration repository tables..."
    wait_for_db_bootstrap
    maybe_reset_test_database_once
    wait_for_db_bootstrap

    echo "Running public schema migrations..."
    run_migrate_with_retry "database/migrations/public" "public"

    echo "Running tenant schema migrations..."
    run_migrate_with_retry "database/migrations/tenant" "shared_tenants"

    echo "Running base seeders (idempotent)..."
    php artisan db:seed --class=DatabaseSeeder --force

    echo "Running gated demo seed (DEMO_SEED_ONCE)..."
    php artisan db:seed --class=DemoCompanyOnceSeeder --force

    mark_test_database_reset_complete
fi

export SERVER_NAME=":${PORT:-8080}"
exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile

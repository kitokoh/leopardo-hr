#!/bin/sh
set -e

echo "Optimizing Laravel at startup..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

run_migrate_with_retry() {
    path="$1"
    attempt=1
    max_attempts=3

    while [ "$attempt" -le "$max_attempts" ]; do
        if php artisan migrate --path="$path" --force --isolated >/tmp/render-migrate.log 2>&1; then
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
                if php artisan migrate --force --isolated >/tmp/render-migrate-catchup.log 2>&1; then
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

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running public schema migrations..."
    run_migrate_with_retry "database/migrations/public"

    echo "Running tenant schema migrations..."
    run_migrate_with_retry "database/migrations/tenant"

    echo "Running base seeders (idempotent)..."
    php artisan db:seed --class=DatabaseSeeder --force

    echo "Running gated demo seed (DEMO_SEED_ONCE)..."
    php artisan db:seed --class=DemoCompanyOnceSeeder --force
fi

export SERVER_NAME=":${PORT:-8080}"
exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile

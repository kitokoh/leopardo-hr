#!/usr/bin/env bash
# Local replica of .github/actions/setup-backend-db — idempotent.
# Usage: bash scripts-local/ci-local-setup.sh
set -euo pipefail
cd "$(dirname "$0")/../api"

cat > .env <<'EOF'
APP_NAME=LeopardoRH
APP_ENV=testing
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=leopardo_test
DB_USERNAME=leopardo_user
DB_PASSWORD=leopardo_pass_test
DB_SEARCH_PATH=shared_tenants,public

CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
EOF

export PGHOST=127.0.0.1 PGPORT=5432 PGDATABASE=leopardo_test PGUSER=leopardo_user PGPASSWORD=leopardo_pass_test

php <<'PHP'
<?php
$pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGHOST'), getenv('PGPORT'), getenv('PGDATABASE')), getenv('PGUSER'), getenv('PGPASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE SCHEMA IF NOT EXISTS shared_tenants;
CREATE TABLE IF NOT EXISTS public.migrations (id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch integer NOT NULL);
CREATE TABLE IF NOT EXISTS shared_tenants.migrations (id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch integer NOT NULL);
SQL);
echo "Migration repositories ensured.\n";
PHP

DB_SEARCH_PATH=public php artisan migrate --path=database/migrations/public --force --isolated
DB_SEARCH_PATH=shared_tenants php artisan migrate --path=database/migrations/tenant --force --isolated
echo "LOCAL CI SETUP OK"

#!/bin/sh
set -e

# =============================================================================
# Leopardo Edge — Docker entrypoint
# =============================================================================

APP_DIR="/app"
DATA_DIR="/data"

# ---------------------------------------------------------------------------
# Préparer le volume de données
# ---------------------------------------------------------------------------
mkdir -p "${DATA_DIR}"
chown -R www-data:www-data "${DATA_DIR}"

# Chemin SQLite (depuis .env ou valeur par défaut)
SQLITE_PATH="${DB_DATABASE:-${DATA_DIR}/leopardo_edge.sqlite}"
touch "${SQLITE_PATH}"
chown www-data:www-data "${SQLITE_PATH}"

# ---------------------------------------------------------------------------
# Générer la clé d'application si absente
# ---------------------------------------------------------------------------
cd "${APP_DIR}"

if [ -z "${APP_KEY}" ]; then
    echo "[edge-entrypoint] APP_KEY absent — génération automatique..."
    APP_KEY=$(php artisan key:generate --show --no-interaction)
    export APP_KEY
fi

# ---------------------------------------------------------------------------
# Migrations Edge (SQLite)
# ---------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[edge-entrypoint] Running Edge migrations..."
    php artisan migrate \
        --path=database/migrations/edge \
        --database=sqlite \
        --force \
        --no-interaction || true
fi

# ---------------------------------------------------------------------------
# Cache Laravel
# ---------------------------------------------------------------------------
php artisan config:cache  --no-interaction
php artisan route:cache   --no-interaction || true
php artisan event:cache   --no-interaction || true

# ---------------------------------------------------------------------------
# Scheduler interne (tourne en arrière-plan)
# ---------------------------------------------------------------------------
echo "[edge-entrypoint] Starting Laravel scheduler (background)..."
(
    while true; do
        php "${APP_DIR}/artisan" schedule:run --no-interaction >> /dev/null 2>&1
        sleep 60
    done
) &

# ---------------------------------------------------------------------------
# Démarrage FrankenPHP
# ---------------------------------------------------------------------------
echo "[edge-entrypoint] Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile

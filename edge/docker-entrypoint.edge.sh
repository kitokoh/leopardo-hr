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
# #7996 : l'image tourne désormais en www-data (non-root) — le chown n'est
# possible que si l'entrypoint s'exécute en root (rechownage manuel d'un
# volume pré-peuplé par l'ancienne version root, cf. edge/Dockerfile).
# En www-data on exige simplement que le volume soit inscriptible : le chown
# du Dockerfile initialise la propriété d'un volume nommé à sa 1re création.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "${DATA_DIR}"
elif [ ! -w "${DATA_DIR}" ]; then
    echo "[edge-entrypoint] ERROR: ${DATA_DIR} n'est pas inscriptible par l'utilisateur courant — volume créé par une version root de l'image ? Le rechownir une fois (voir edge/Dockerfile, #7996)." >&2
    exit 1
fi

# Chemin SQLite (depuis .env ou valeur par défaut)
SQLITE_PATH="${DB_DATABASE:-${DATA_DIR}/leopardo_edge.sqlite}"
touch "${SQLITE_PATH}"
if [ "$(id -u)" = "0" ]; then
    chown www-data:www-data "${SQLITE_PATH}"
fi

# ---------------------------------------------------------------------------
# Générer la clé d'application si absente
# ---------------------------------------------------------------------------
cd "${APP_DIR}"

ENV_FILE="${DATA_DIR}/.env"

# Issue #3592 : persister APP_KEY dans le volume /data — sans cela, chaque
# redémarrage hors install.sh régénère une clé éphémère et invalide données
# chiffrées et sessions.
if [ -z "${APP_KEY}" ] && [ -f "${ENV_FILE}" ]; then
    # Redémarrage : recharger la clé persistée au premier boot.
    # shellcheck disable=SC1090
    . "${ENV_FILE}"
    export APP_KEY
fi

if [ -z "${APP_KEY}" ]; then
    echo "[edge-entrypoint] APP_KEY absent — génération automatique..."
    APP_KEY=$(php artisan key:generate --show --no-interaction)
    export APP_KEY
    touch "${ENV_FILE}"
    if [ "$(id -u)" = "0" ]; then
        chown www-data:www-data "${ENV_FILE}"
    fi
    if ! grep -q '^APP_KEY=' "${ENV_FILE}"; then
        printf 'APP_KEY=%s\n' "${APP_KEY}" >> "${ENV_FILE}"
    fi
fi

# ---------------------------------------------------------------------------
# Échecs visibles (issue #3966) — un device sans surveillance doit montrer
# ses erreurs : exit non nul pour les étapes critiques, fichier d'état
# d'erreur pour les étapes non bloquantes, logs scheduler sur volume.
# ---------------------------------------------------------------------------
ERROR_STATE_FILE="${DATA_DIR}/edge-entrypoint-error"

log_error_and_state() {
    echo "[edge-entrypoint] ERROR: $*" >&2
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*" >> "${ERROR_STATE_FILE}"
}

run_critical() {
    # Étape critique : l'échec stoppe le boot (le conteneur boucle en
    # restart, visible dans `docker compose ps` / healthcheck).
    if ! "$@"; then
        log_error_and_state "critical step failed: $*"
        exit 1
    fi
}

run_soft() {
    # Étape non bloquante : l'échec est loggé + persisté (le device boote
    # quand même, l'opérateur trouve la trace dans /data).
    if ! "$@"; then
        log_error_and_state "non-critical step failed: $*"
    fi
}

# ---------------------------------------------------------------------------
# Migrations Edge (SQLite) — critique : un schéma cassé rend le pointage
# hors-ligne inutilisable en silence (avant : `|| true` avalait tout).
# ---------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[edge-entrypoint] Running Edge migrations..."
    # #4411 + #6604 : le chemin fantôme database/migrations/edge a été
    # supprimé (miroir dédupliqué) — source unique = la migration tenant
    # 2026_06_29_000001. Les 4 tables
    # edge (edge_nodes, sync_logs, sync_queue, edge_licenses) sont créées par
    # la migration tenant 2026_06_29_000001, autonome et SQLite-compatible
    # (aucun resolveTableSchema/search_path). Les autres migrations tenant
    # sont PostgreSQL (ledger…) et ne doivent PAS tourner sur SQLite.
    run_critical php artisan migrate \
        --path=database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php \
        --database=sqlite \
        --force \
        --no-interaction
fi

# ---------------------------------------------------------------------------
# Cache Laravel — non bloquant (Caddy sert les routes sans route:cache)
# ---------------------------------------------------------------------------
run_soft php artisan config:cache --no-interaction
run_soft php artisan route:cache --no-interaction
run_soft php artisan event:cache --no-interaction

# ---------------------------------------------------------------------------
# Scheduler interne (tourne en arrière-plan) — logs persistés sur le volume
# (avant : `>> /dev/null 2>&1` perdait toutes les erreurs de tâches).
# ---------------------------------------------------------------------------
echo "[edge-entrypoint] Starting Laravel scheduler (background)..."
mkdir -p "${DATA_DIR}/logs"
(
    while true; do
        if ! php "${APP_DIR}/artisan" schedule:run --no-interaction >> "${DATA_DIR}/logs/edge-scheduler.log" 2>&1; then
            echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] schedule:run failed (see edge-scheduler.log)" >> "${ERROR_STATE_FILE}"
        fi
        sleep 60
    done
) &

# ---------------------------------------------------------------------------
# Démarrage FrankenPHP
# ---------------------------------------------------------------------------
echo "[edge-entrypoint] Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile

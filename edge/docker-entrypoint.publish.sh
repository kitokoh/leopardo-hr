#!/bin/sh
# #4411 — image publiée leopardo/edge-api : le schéma SQLite n'était JAMAIS
# provisionné (CMD supervisord sans étape migrate) → file sync_queue vide et
# EdgeSyncDaemon en boucle « no such table » silencieuse. Migrations exécutées
# avant le démarrage de nginx/php-fpm, même chemin que docker-entrypoint.edge.sh.
set -e

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[publish-entrypoint] Running Edge migrations (SQLite)..."
    php artisan migrate \
        --path=database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php \
        --database=sqlite \
        --force \
        --no-interaction
fi

exec "$@"

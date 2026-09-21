# =============================================================================
# Leopardo Edge API — Docker image (standalone, FrankenPHP)
# Tag cible : leopardo/edge-api:1.0.0
#
# STATUS: standalone reference image, not currently wired into
# edge/docker-compose.yml (which builds edge-api/edge-ui from Dockerfile.edge)
# nor into edge/publish.sh (which uses Dockerfile.publish). Build it manually
# from the repo root if you need the FrankenPHP + embedded PWA variant.
# See the other Dockerfile* files in this directory for their actual role:
#   - Dockerfile.edge    -> used by edge/docker-compose.yml (local/dev stack)
#   - Dockerfile.publish -> used by edge/publish.sh (Docker Hub release image)
#
# Build :
#   docker build -t leopardo/edge-api:1.0.0 -f edge/Dockerfile .
#
# #7996 : image 100 % non-root (USER www-data), Caddy en :8080 —
# docker run -p 8080:8080 ... (avant : root + :80/:443).
#
# L'image embarque :
#   - L'API Laravel (FrankenPHP + PHP 8.4)
#   - La PWA web offline (front/web-offline) servie en statique par Caddy
#   - SQLite pour la base de données locale (pas de Postgres requis)
#   - Un scheduler intégré (cron interne) pour edge:detect-silent-nodes
# =============================================================================

FROM dunglas/frankenphp:php8.4-alpine AS base

LABEL maintainer="Leopardo RH <dev@leopardo-rh.com>"
LABEL org.opencontainers.image.title="Leopardo Edge API"
LABEL org.opencontainers.image.version="1.0.0"
LABEL org.opencontainers.image.description="Nœud Edge offline pour Leopardo RH — pointages hors connexion + sync cloud"

# ---------------------------------------------------------------------------
# PHP extensions
# ---------------------------------------------------------------------------
RUN install-php-extensions \
    pcntl \
    pdo_sqlite \
    sqlite3 \
    gd \
    intl \
    zip \
    bcmath \
    opcache \
    redis \
    sockets

# ---------------------------------------------------------------------------
# Runtime env defaults (surchargeables via .env ou docker-compose)
# ---------------------------------------------------------------------------
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/leopardo_edge.sqlite \
    EDGE_ENABLED=true \
    RUN_MIGRATIONS=true \
    CADDY_GLOBAL_OPTIONS="" \
    # #7996 : port non privilégié (image non-root, cf. USER plus bas) —
    # cohérent avec edge/Caddyfile.standalone. Sans Caddyfile custom, ce
    # SERVER_NAME est la valeur utilisée par le Caddyfile par défaut de
    # l'image FrankenPHP.
    SERVER_NAME="leopardo.local, :8080"

# ---------------------------------------------------------------------------
# Build stage : dépendances Composer
# ---------------------------------------------------------------------------
WORKDIR /app

COPY api/ /app/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# PWA web offline — build stage (issue #3965)
# front/web-offline est un Next.js en static export (`output: 'export'` → out/) :
# copier l'arbre source brut livrait des routes 404 et des .tsx en texte brut
# (aucun index.html dans le source ; out/ est gitignoré).
# ---------------------------------------------------------------------------
FROM node:20-alpine AS pwa-build
WORKDIR /pwa
COPY front/web-offline/package.json front/web-offline/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY front/web-offline/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Copie du static export de la PWA web offline (out/ + public/ fusionnés par
# Next) — Caddyfile.standalone le sert avec fallback SPA → /index.html
# ---------------------------------------------------------------------------
COPY --from=pwa-build /pwa/out/ /app/public/edge-web/

# ---------------------------------------------------------------------------
# Caddyfile Edge (standalone) — sert PWA + API dans le même conteneur
# (issue #3592 : Caddyfile.edge proxyait vers edge-ui/edge-api inexistants ici)
# ---------------------------------------------------------------------------
COPY edge/Caddyfile.standalone /etc/caddy/Caddyfile

# ---------------------------------------------------------------------------
# Entrypoint
# ---------------------------------------------------------------------------
COPY --chmod=755 edge/docker-entrypoint.edge.sh /usr/local/bin/docker-entrypoint.sh

# ---------------------------------------------------------------------------
# #7996 — image 100 % non-root (rattrapage du lot #7966, qui couvrait
# Dockerfile.edge / Dockerfile.publish mais pas cette variante standalone).
# Tout tourne en www-data : entrypoint (ses chowns sont devenus conditionnels,
# cf. docker-entrypoint.edge.sh), FrankenPHP/Caddy inclus. À préparer :
#   - /data   : volume SQLite + .env persisté + données Caddy (XDG_DATA_HOME)
#   - /config : autosave Caddy (XDG_CONFIG_HOME)
# Le chown au build initialise la propriété d'un volume nommé à sa 1re
# création ; un volume /data créé par une ancienne version root de l'image
# doit être rechowné une fois (`docker run --rm --user root -v <vol>:/data
# <image> chown -R www-data:www-data /data`) — l'entrypoint le signale.
# ---------------------------------------------------------------------------
RUN mkdir -p /data /config \
    && chown -R www-data:www-data /data /config

# Volume pour persister la base SQLite + licences + clés
VOLUME ["/data"]

# #7996 — port non privilégié, cohérent avec Caddyfile.standalone (:8080).
# L'ancien `EXPOSE 80 443` datait du fonctionnement en root : Caddy n'écoute
# plus que sur :8080 (HTTP simple, cf. edge/Caddyfile.standalone).
EXPOSE 8080

USER www-data

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

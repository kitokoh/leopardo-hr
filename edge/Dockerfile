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
    SERVER_NAME="leopardo.local, :80"

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

# Volume pour persister la base SQLite + licences + clés
VOLUME ["/data"]

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

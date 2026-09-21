FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql mbstring zip gd bcmath intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------------------
# #7996 — exécution non-root (aligné sur #7966 côté Edge).
# Cette image n'est utilisée que par les services dev api/queue/scheduler de
# docker-compose.yml, avec le code monté en bind-mount (`./api:/var/www/html`).
# Choix de l'uid : 1000/1000 par défaut — uid/gid du premier utilisateur sur
# les postes Linux, donc celui qui possède le clone : les fichiers écrits
# depuis le conteneur (vendor/, storage/logs…) restent modifiables par
# l'hôte, et réciproquement. Sous Docker Desktop (macOS/Windows), la
# correspondance d'uid est gérée par la couche de partage de fichiers.
# Poste avec un uid différent → aligner l'uid au build (les args sont
# exposés dans docker-compose.yml) :
#   APP_UID=$(id -u) APP_GID=$(id -g) docker compose up -d --build
# (c'est la voie utilisée par la CI onboarding-smoke, runner uid 1001 ;
# pattern de référence non-root du dépôt : edge/Dockerfile.edge, #7966.)
# ---------------------------------------------------------------------------
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupadd --gid "${APP_GID}" leopardo \
    && useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /bin/bash leopardo

# HOME explicite : la directive USER ne le positionne pas à elle seule, et
# composer (cache) comme le DevContainer VS Code en ont besoin.
ENV HOME=/home/leopardo

USER leopardo

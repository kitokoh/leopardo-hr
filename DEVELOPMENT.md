# Development Guide — Leopardo

Guide pour les nouveaux contributeurs. Pour les regles agent/CI, voir `AGENTS.md`.

## Prerequisites

- **Docker** >= 24 + **Docker Compose** v2
- **Node.js** >= 20 (pour le dashboard admin et la vitrine Next.js)
- **PHP** >= 8.4.1 + Composer >= 2.6 (pour le backend Laravel — le lock Composer contient des composants Symfony 8.x qui exigent PHP 8.4.1+, cf. issue #3819)
- **Flutter** >= 3.22 (pour l'application mobile)

### Session d'agent sans root (image sans PHP) — issue #7585

L'image d'agent ne porte ni PHP ni composer. Sans eux, les checks requis
`PHPStan — Strict` et les gardes PHP ne peuvent pas être rejoués localement :
chaque itération coûte un cycle CI de 10 à 20 min. Un script les reconstitue,
**sans root** et sans rien installer dans `/` :

```bash
# 1. installer (idempotent — relancer ne re-télécharge rien)
bash dev-hub/tools/bootstrap-local-php.sh

# 2. activer dans la session courante
source "${XDG_CACHE_HOME:-$HOME/.cache}/leopardo/php/env.sh"

# 3. rejouer la commande EXACTE du check requis
cd api && composer install --no-interaction --prefer-dist --optimize-autoloader
cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=1G --no-progress
```

Diagnostic à tout moment : `bash dev-hub/tools/bootstrap-local-php.sh --check`.

Deux points valent d'être connus avant de déboguer ce script :

- **`PHPRC` est indispensable.** Composer relance PHP pour ses scripts
  (`@php artisan package:discover`) via `PHP_BINARY`, **sans propager le `-c`**
  qui l'a lancé. Sans `PHPRC`, l'enfant démarre sans `php.ini` : ni `tokenizer`
  ni `mbstring`, et l'échec est trompeur (`Call to undefined function
  NunoMaduro\Collision\token_get_all()`, enveloppé dans Whoops). Le `env.sh`
  généré pose donc `PHPRC`, et les shims `php`/`composer` le posent eux-mêmes.
- **`zip` peut manquer** (`libzip.so.4` absent du système) — ce n'est pas
  bloquant : `api/composer.json` n'exige aucune `ext-*`, et composer retombe sur
  la commande `unzip`. Le script écarte l'extension et le dit.

## Quick Start (Docker)

```bash
# 1. Clone the repo
git clone https://github.com/kitokoh/leopardo-hr.git
cd leopardo-hr

# 2. Copy environment
cp api/.env.example api/.env

# 3. Start all services
make install      # = docker compose up -d --build + migrate + seed
# OR manually:
docker compose up -d --build
docker compose exec api php artisan leopardo:migrate --seed

# 4. Access
# Backend API:      http://localhost:8000/api/v1/health
# Admin dashboard:  http://localhost:5173 (after npm run dev)
# Vitrine Next.js:  http://localhost:3000 (after npm run dev)
```

> **Recette sur un environnement déployé (issue #7304)** : un environnement peut
> répondre `200` en servant un commit vieux de 50 PR. Avant toute campagne QA,
> vérifiez que la version **réellement servie** est celle de `main` :
>
> ```bash
> dev-hub/tools/check-deploy-drift.sh --url "$DEV_API_BASE_URL" --expect origin/main --label dev
> ```
>
> `0` = aligné, `1` = dérive (le script nomme les deux SHAs), `2` = environnement
> injoignable. Runbook : `docs/ops/RENDER_DEV_ALIGNMENT.md`.

### Migration d'un poste dev existant (issues #7996 / #8018)

Trois pièges après le passage des images dev en non-root et des ports en
loopback (#7847 / #7996) :

- **`POSTGRES_PASSWORD` ne s'applique qu'au PREMIER `initdb`.** Le mot de passe
  est écrit dans le volume `pgdata` à sa création : le changer dans
  `.env`/le compose ne le met pas à jour sur un volume existant, et le service
  `api` tente alors le NOUVEAU mot de passe → `password authentication failed`
  (le conteneur postgres, lui, n'écoute pas la variable après l'initdb). Volume
  déjà initialisé avec l'ancien `secret` : soit garder ce mot de passe
  (`POSTGRES_PASSWORD=secret` dans l'environnement ou dans un `.env` racine, non
  versionné), soit recréer le volume et repartir d'une base neuve
  (`make destroy` = `docker compose down -v`, puis `make install`).
- **`api/vendor` et `api/storage` appartiennent à root.** Si la stack a tourné
  avant #7996 (images root), les fichiers créés dans le bind-mount `./api` sont
  root-owned et l'utilisateur non-root `leopardo` (uid 1000) de l'image ne peut
  plus écrire (composer, logs, cache). Remède :
  `sudo chown -R $(id -u):$(id -g) api/`. Si l'uid de l'hôte n'est pas 1000,
  alignez aussi l'image : `APP_UID=$(id -u) APP_GID=$(id -g) docker compose up -d --build`.
- **Perte de l'accès LAN.** Depuis #7847/#7996, TOUS les ports de dev (api 8000,
  dashboard 3000, web 3001, postgres, redis, mailpit) sont publiés sur
  `127.0.0.1` uniquement : un téléphone ou une tablette du réseau local ne peut
  plus joindre la stack via l'IP de la machine (c'est volontaire — les mots de
  passe de dev sont publics sur ce compose). Workaround local et non versionné,
  `docker-compose.override.yml` à la racine du dépôt :

  ```yaml
  # Republie le port sur toutes les interfaces. `!override` (Compose >= 2.24)
  # REMPLACE la liste `ports` du compose principal ; avec un Compose plus ancien
  # (listes concaténées), utilisez un port hôte distinct, ex. "8001:8000"
  # (joignable via http://<ip-de-la-machine>:8001).
  services:
    api:
      ports: !override
        - "8000:8000"
  ```

  Dupliquez le bloc pour les autres services utiles (dashboard, web…). À
  réserver aux réseaux de confiance : cette stack de dev n'a pas de secrets.

## Project Structure

```
leopardo-hr/
├── api/                    # Backend Laravel 12 (^12.60)
│   ├── app/
│   ├── config/
│   ├── database/migrations/
│   ├── routes/
│   └── tests/
├── front/
│   ├── admin-dashboard/    # Vue.js 3.4 + Pinia + Tailwind (plateforme admin)
│   ├── web/                # Next.js 16 (vitrine + blog + SEO)
│   └── mobile_apps/        # Apps Flutter (leopardo_core, leopardo_employee, leopardo_manager, leopardo_hr, leopardo_marketing, leopardo_accounting, leopardo_platform_admin, leopardo_travel_agent)
├── docker-compose.yml
├── Makefile
├── .devcontainer/          # VS Code DevContainer
└── docs/
    ├── archive/PLAN_ACTION/ # Plans d'action historiques (clos, voir PLAN_ACTION2/)
    └── api/                # Documentation OpenAPI
```

## Backend (Laravel)

```bash
cd api

# Install dependencies
composer install

# Run migrations (public + tenant schemas — see note below)
php artisan leopardo:migrate --seed

# Run tests
php artisan test
# Or specific suite:
php artisan test --filter=PayrollControllerTest

# Static analysis
./vendor/bin/phpstan analyse --memory-limit=512M

# Code style
./vendor/bin/pint --test
./vendor/bin/pint  # fix
```

> **Pourquoi `leopardo:migrate` et pas `artisan migrate` ?**
> Leopardo utilise un modele multi-tenant hybride a deux schemas PostgreSQL : `public`
> (tables partagees) et `shared_tenants` (tables metier : employes, contrats, paie, presence...).
> `artisan migrate` seul ne lit que `database/migrations/` a la racine et **ne cree jamais le
> schema `shared_tenants`**. La commande custom `leopardo:migrate` (voir `api/routes/console.php`)
> bascule le `search_path` et joue les migrations `database/migrations/public/` puis
> `database/migrations/tenant/` dans le bon ordre. Utilisez toujours `leopardo:migrate` (avec
> `--fresh`/`--seed`/`--demo` au besoin) en local, jamais `migrate`/`migrate:fresh` nu — voir
> `docs/architecture/MULTITENANCY.md` pour le detail du modele.

### Environment Variables

Key variables for `api/.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `SENTRY_LARAVEL_DSN` | Sentry APM DSN | _(optional)_ |
| `SENTRY_TRACES_SAMPLE_RATE` | Performance trace rate | `0.2` |
| `LOG_SLACK_WEBHOOK_URL` | Slack alerting webhook | _(optional)_ |
| `LOG_DISCORD_WEBHOOK_URL` | Discord alerting webhook | _(optional)_ |

## Admin Dashboard (Vue.js)

```bash
cd front/admin-dashboard

npm install
npm run dev       # http://localhost:5173
npm run build     # Production build
npm run lint      # ESLint
```

### Conventions

- Vue 3 Composition API with `<script setup>`
- Tailwind CSS for styling
- `@heroicons/vue/24/outline` for icons
- Axios via `src/services/api.js` (auto token injection)
- Pinia stores in `src/stores/`
- Views in `src/views/`, components in `src/components/`

## Vitrine (Next.js)

```bash
cd front/web

npm install
npm run dev       # http://localhost:3000
npm run build
```

### Blog Articles

Blog articles are MDX files in `src/content/blog/`. Each file needs frontmatter:

```md
---
title: "Mon article"
date: "2026-01-15"
author: "Nom Auteur"
excerpt: "Description courte"
tags: ["rh", "paie"]
---

Content here...
```

## Mobile (Flutter)

Voir [`front/mobile_apps/README.md`](front/mobile_apps/README.md) pour le detail par app
(`leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_marketing`, `leopardo_platform_admin`).

```bash
cd front/mobile_apps/leopardo_employee

flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

### Architecture

- **Riverpod** for state management (`FutureProvider`, `StateNotifier`)
- **GoRouter** for navigation
- **Dio** HTTP client via `core/api/api_client.dart`
- Feature-based structure: `features/{name}/data/`, `providers/`, `screens/`

## Running Tests

```bash
# Backend (from repo root)
make test
# Or: cd api && php artisan test

# Admin dashboard
cd front/admin-dashboard && npm test

# Mobile (par app, ex. leopardo_employee)
cd front/mobile_apps/leopardo_employee && flutter test
```

## CI/CD

GitHub Actions workflows:

| Workflow | Trigger | Checks |
|----------|---------|--------|
| `tests.yml` | `api/**` changes | PHPUnit/Pest, PHPStan, Pint, sécurité backend (pas de job front — admin-dashboard couvert par `web-ci.yml`) |
| `coverage-gate.yml` | `api/**` changes | Coverage >= threshold — informatif, **non requis au merge** depuis #6928 (reste exigé à la release, voir `BRANCH_PROTECTION_REQUIRED.md`) |
| `mobile-apps-ci.yml` | `front/mobile_apps/**` changes | Flutter analyze + test + APK |
| `web-ci.yml` | `front/admin-dashboard/**` changes | ESLint + Vite build |
| `web-marketing-ci.yml` | `front/web/**` changes | Lint + Next.js build |
| `deploy-staging.yml` | Merge to `main` | Auto deploy staging |
| `e2e-staging.yml` | `workflow_run` après « Deploy - Leopardo » (`deploy-main.yml`) | Smoke E2E Playwright (nom historique ; contenu « Prod Smoke ») |
| `release.yml` | Git tag `v*` | GitHub Release (mobile APKs are built/distributed separately by `mobile-distribute.yml`) |

> Il n'existe pas de fichier `backend.yml` distinct dans `.github/workflows/` : les checks backend PHPUnit/PHPStan/Pint sont dans `tests.yml`.

## Contributing

1. Fork the repo
2. Create a branch from `main`: `git checkout -b feat/my-feature`
3. Make changes, add CHANGELOG entry
4. Push and create a PR
5. Wait for CI checks to pass
6. Request review

### Good First Issues

Look for issues labeled [`good first issue`](https://github.com/kitokoh/leopardo-hr/labels/good%20first%20issue) for beginner-friendly tasks.

## Architecture Decisions

- **Multi-tenant** : Each company gets its own PostgreSQL schema
- **RBAC** : Role-based access with `admin`, `super_admin`, `manager` (principal/rh/departement/superviseur), `employee`
- **Payroll** : Country-specific rules via `AbstractCountryRules` (DZ, MA, SN, TR supported)
- **AI** : `App\AI\Orchestrator` for LLM routing (not `AIOrchestrator` — see AGENTS.md)

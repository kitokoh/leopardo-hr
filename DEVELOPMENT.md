# Development Guide — Leopardo RH

Guide pour les nouveaux contributeurs. Pour les regles agent/CI, voir `AGENTS.md`.

## Prerequisites

- **Docker** >= 24 + **Docker Compose** v2
- **Node.js** >= 20 (pour le dashboard admin et la vitrine Next.js)
- **PHP** >= 8.2 + Composer >= 2.6 (pour le backend Laravel)
- **Flutter** >= 3.22 (pour l'application mobile)

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
│   └── mobile_apps/        # Apps Flutter (leopardo_core, leopardo_employee, leopardo_manager, leopardo_hr, leopardo_platform_admin)
├── docker-compose.yml
├── Makefile
├── .devcontainer/          # VS Code DevContainer
└── docs/
    ├── archive/PLAN_ACTION/ # Plans d'action historiques (clos, voir PLAN_ACTION2/)
    └── API/                # Documentation OpenAPI
```

## Backend (Laravel)

```bash
cd api

# Install dependencies
composer install

# Run migrations (public + tenant schemas — see "Why leopardo:migrate" below)
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

### Why `leopardo:migrate` and not `artisan migrate`

Leopardo RH is multi-tenant: each request is served from one of **two PostgreSQL schemas**
(`public` for shared/platform tables, `shared_tenants` for the ~71 tenant tables — employees,
contracts, payroll, attendance, etc. — see `docs/architecture/MULTITENANCY.md`).

Laravel's native `artisan migrate` / `migrate:fresh` only reads `database/migrations/` at the
project root by default. On this codebase that directory only contains 2 files; the other 94
migrations live under `database/migrations/public/` and `database/migrations/tenant/`, which are
never picked up without an explicit `--path`. Running plain `artisan migrate` silently succeeds
but never creates the tenant schema, so the API answers on `/health` and then 500s on every
business route (employees, payroll, attendance...).

The custom command `leopardo:migrate` (defined in `api/routes/console.php`) runs both migration
paths in the correct order and toggles PostgreSQL's `search_path` between `public` and
`shared_tenants` accordingly. **Always use `leopardo:migrate` (or `make migrate` /
`make migrate-fresh`) instead of bare `artisan migrate`** for local onboarding, seeding, and
resets. Options: `--fresh` drops both schemas first, `--seed` runs `DatabaseSeeder`, `--demo`
additionally seeds demo company data (local/dev only).

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
(`leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`).

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
| `tests.yml` | `api/**` or `front/admin-dashboard/**` changes | PHPUnit/Pest, PHPStan, Pint + admin-dashboard lint/build |
| `coverage-gate.yml` | `api/**` changes | Coverage >= threshold |
| `mobile-apps-ci.yml` | `front/mobile_apps/**` changes | Flutter analyze + test + APK |
| `web-ci.yml` | `front/admin-dashboard/**` changes | ESLint + Vite build |
| `web-marketing-ci.yml` | `front/web/**` changes | Lint + Next.js build |
| `deploy-staging.yml` | Merge to `main` | Auto deploy staging |
| `e2e-staging.yml` | After staging deploy | Playwright E2E |
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

# Development Guide — Leopardo RH

> 📌 **Portee de ce document** : version destinee aux developpeurs et integrateurs
> externes (`dev-hub/`). Pour le developpement interne au monorepo, la reference qui prime
> reste [`/DEVELOPMENT.md`](../DEVELOPMENT.md) a la racine du depot. En cas de divergence
> entre les deux fichiers (versions de prerequis, structure), la version racine est canonique.

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.4+ | With extensions: pdo_pgsql, redis, mbstring, openssl, bcmath |
| Composer | 2.x | |
| PostgreSQL | 16+ | |
| Node.js | 20+ | For admin-dashboard and web |
| Redis | 7+ | Optional for local dev (queue, cache) |
| Flutter | 3.x | For mobile app only |

## Quick Start (Docker)

```bash
cd api
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan leopardo:migrate --seed
docker compose exec app php artisan serve --host=0.0.0.0 --port=8000
```

The API is available at `http://localhost:8000/api/v1/health`.

## Quick Start (Local)

```bash
# 1. Clone
git clone https://github.com/kitokoh/leopardo-hr.git
cd leopardo-hr/api

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Configure your .env
#    DB_CONNECTION=pgsql
#    DB_HOST=127.0.0.1
#    DB_DATABASE=leopardo
#    DB_USERNAME=your_user
#    DB_PASSWORD=your_password

# 5. Run migrations (public + tenant schemas — plain `artisan migrate` will NOT create
#    the shared_tenants schema, see docs/architecture/MULTITENANCY.md)
php artisan leopardo:migrate --seed

# 6. Start the server
php artisan serve
```

## Project Structure

```
api/                    # Laravel backend (PHP 8.4) — monolithe modulaire DDD
  app/
    Core/               # Socle transversal (Auth, Tenant, Feature)
    Shared/             # Code partagé (traits, enums, helpers)
    Modules/{Module}/   # Modules DDD (voir api/ARCHITECTURE.md)
      Domain/           # Models, Events, Enums, Exceptions, ValueObjects
      Application/      # DTOs, Actions, Queries, Listeners
      Infrastructure/   # Repositories, Services, Exports
      Interfaces/       # Controllers, Requests, Resources
      Providers/        # ServiceProvider du module
    Http/
      Middleware/       # Middlewares HTTP
      Resources/Api/V1/ # JsonResource centralisées (dérogation PA2-ARCH-010)
  database/migrations/  # Laravel migrations
  routes/
    api.php             # Routes principales
    modules/            # Routes par module
  tests/                # Pest/PHPUnit tests
front/
  admin-dashboard/      # Vue 3 + Vite — dashboard super-admin plateforme
  web/                  # Next.js — vitrine + portail client (déployé sur Vercel)
  web-offline/          # Next.js — PWA offline-first pour le bridge Edge
  mobile_apps/          # Apps Flutter (leopardo_core, leopardo_employee, leopardo_manager, leopardo_hr, leopardo_marketing, leopardo_platform_admin, leopardo_accounting)
  zkteco-kiosk/         # Kiosque de pointage + pont local Python (desktop-bridge)
edge/                   # Bridge on-prem ZKTeco ↔ cloud (Caddy, supervisord, install.sh)
shared/                 # Ressources partagées (i18n, mediaForMarketing)
docs/                   # Toute la documentation
```

## Creating a New Module

```bash
cd api
php artisan make:module MyModule
```

This generates the full DDD directory structure under `app/Modules/MyModule/`.

## Running Tests

```bash
cd api
php artisan test                    # All tests
php artisan test --filter=HealthEndpointTest  # Specific test
```

## Code Style

- PSR-12 strict (Laravel Pint)
- `declare(strict_types=1)` in all new files
- Explicit return types on all public methods
- FormRequest for all input validation
- API Resource for all JSON serialization
- Policy for all authorization

```bash
cd api
./vendor/bin/pint          # Fix code style
./vendor/bin/phpstan       # Static analysis
```

## CI/CD

All checks run on GitHub Actions:
- **Backend**: `tests.yml` — PHPUnit/Pest, PHPStan, Pint
- **Web**: `web-ci.yml` — lint, build (Next.js)
- **Admin**: `web-ci.yml` (build) + `e2e-staging.yml` (Playwright)
- **Mobile**: `mobile-apps-ci.yml` — déclenché sur `front/mobile_apps/**`
- **Security**: `secret-scan.yml`, `codeql.yml`

## API Health Endpoints

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /api/v1/health` | None | Full health check (DB, Redis, storage) |
| `GET /api/v1/health/live` | None | Liveness probe (always 200) |
| `GET /api/v1/health/ready` | None | Readiness probe (DB check) |

## Useful Commands

```bash
php artisan make:module {Name}     # Scaffold a DDD module
php artisan leopardo:migrate        # Run migrations (public + tenant schemas)
php artisan leopardo:migrate --fresh --seed   # Reset and seed
php artisan route:list             # List all routes
php artisan tinker                 # REPL
```

## Environment Variables

Key variables in `.env`:

| Variable | Description |
|----------|-------------|
| `APP_ENV` | `local`, `staging`, `production` |
| `DB_CONNECTION` | `pgsql` |
| `SENTRY_DSN` | Sentry error tracking DSN |
| `SENTRY_TRACES_SAMPLE_RATE` | APM sample rate (0.0 to 1.0) |
| `REDIS_URL` | Redis connection URL (optional) |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

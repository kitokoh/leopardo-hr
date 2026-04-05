# RUNBOOK - DEPLOY PRODUCTION
# Version 4.1.3 | 04 Avril 2026

## Preconditions
- CI `tests.yml` verte sur `main`
- Backup PostgreSQL valide (< 1h)
- Fenetre de deploiement annoncee

## Procedure
1. Activer maintenance mode (`php artisan down`)
2. Pull `main`
3. `composer install --no-dev --optimize-autoloader`
4. Backup DB `pg_dump -Fc`
5. `php artisan migrate --force`
6. Rebuild caches (`config`, `route`, `view`, `event`)
7. Verifier permissions `storage/` et `bootstrap/cache/`
8. `php artisan up`
9. Health check `/api/v1/health`
10. Verifier `/login` puis `/dashboard`

## Validation post-deploy
- HTTP 200 health
- Login manager OK
- Dashboard manager OK
- PDF estimation OK
- Endpoint attendance OK
- Session web OK

# RUNBOOK BETA ENV SETUP
# Version 1.1 | 2026-05-03

But: preparer un environnement Beta exploitable sur Render (PaaS) avant la recette humaine.

## Checklist infrastructure (Render + Neon)

- Web Service cree sur Render (Docker context: `api/`, Dockerfile: `Dockerfile.prod`)
- Base de donnees creee sur Neon.tech
- URL de connexion Neon (PostgreSQL) recuperee
- TLS (HTTPS) actif par defaut sur Render
- `DB_URL` configuree sur Render avec l'URL Neon
- `DB_SEARCH_PATH` configure a `shared_tenants,public`
- `APP_KEY` generee et configuree
- `RUN_MIGRATIONS=true` pour le premier deploiement

## Checklist application

- Code de `main` deploie via GitHub
- `composer install` automatique via Docker build
- `php artisan migrate --force` (gere par `api/docker-entrypoint.sh` si `RUN_MIGRATIONS=true`)
- `php artisan config:cache` (gere au startup)
- `php artisan route:cache` (gere au startup)
- `php artisan view:cache` (gere au startup)

## Variables d'environnement MVP attendues

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=pgsql`
- `DB_URL=postgresql://...`
- `DB_SEARCH_PATH=shared_tenants,public`
- `CACHE_STORE=file`
- `SESSION_DRIVER=file`
- `QUEUE_CONNECTION=sync`
- `FILESYSTEM_DISK=local`
- `TENANCY_DEFAULT_TYPE=shared`

## Donnees minimales de recette

- 1 company active (via `db:seed` ou `DemoCompanyOnceSeeder`)
- 1 manager actif
- 1 employee actif
- logs de pointage existants pour verifier dashboard, historique et PDF

## Verification express

- `GET /api/v1/health` -> 200, `status=ok`
- `GET /login` -> 200
- login manager -> redirect `/dashboard`
- fiche employe accessible
- quick estimate OK
- PDF OK

## Blocage immediat

Stop Beta si:
- login impossible
- erreur 500 sur dashboard
- PDF KO
- fuite tenant
- RBAC manager/employee incoherent

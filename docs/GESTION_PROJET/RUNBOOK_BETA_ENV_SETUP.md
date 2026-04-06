# RUNBOOK BETA ENV SETUP
# Version 1.0 | 2026-04-06

But: preparer un environnement Beta exploitable avant la recette humaine.

## Checklist infrastructure

- VPS cree et accessible en SSH
- Domaine ou sous-domaine pointe vers le VPS
- TLS actif
- PostgreSQL accessible
- PHP/FPM et serveur web actifs
- dossier `storage/` accessible en ecriture
- dossier `bootstrap/cache/` accessible en ecriture

## Checklist application

- Code de `main` deploie
- `composer install --no-dev --optimize-autoloader` execute
- `php artisan key:generate` execute si environnement neuf
- `php artisan migrate --force` execute
- `php artisan config:cache` execute
- `php artisan route:cache` execute
- `php artisan view:cache` execute

## Variables d'environnement MVP attendues

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=pgsql`
- `CACHE_STORE=file`
- `SESSION_DRIVER=file`
- `QUEUE_CONNECTION=sync`
- `FILESYSTEM_DISK=local`
- `TENANCY_DEFAULT_TYPE=shared`

## Donnees minimales de recette

- 1 company active
- 1 manager actif
- 1 employee actif
- 1 ou 2 logs de pointage existants pour verifier dashboard, historique et PDF

## Verification express

- `GET /api/v1/health` -> 200
- `GET /login` -> 200
- login manager -> redirect `/dashboard`
- fiche employe accessible
- quick estimate OK
- PDF OK

## Blocage immediate

Stop Beta si:
- login impossible
- erreur 500 sur dashboard
- PDF KO
- fuite tenant
- RBAC manager/employee incoherent

# Leopardo RH - Backend API (Laravel 11)

## Stack
- Laravel 11 + PostgreSQL 16
- Auth API : Laravel Sanctum
- Auth web : session manager
- Cache : file
- Queue : sync
- Web : Blade + Alpine.js

## Setup local developpeur (Docker recommande)

```bash
cd api
docker compose up -d
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan test
```

### Démarrage 1-commande (Windows PowerShell)

```powershell
cd api
.\start-local.ps1 -SeedDemo
```

Options utiles:
- `-Rebuild` : reconstruit l'image Docker
- `-SeedDemo` : injecte les comptes de démonstration (manager inclus)
- `-RunTests` : lance `php artisan test` en fin de script

## Setup local manuel (hors Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Checks utiles

```bash
php artisan test
php artisan route:list
```

## References
- Contrats API : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- ERD : `../docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- Regles metier : `../docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- Prompt MVP actif : `../docs/PROMPTS_EXECUTION/v3/MVP-05_DASHBOARD_WEB.md`

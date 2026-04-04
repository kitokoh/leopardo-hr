# Leopardo RH — Backend API (Laravel 11)

## Stack
- Laravel 11 + PostgreSQL + Redis
- Auth : Laravel Sanctum (tokens opaques)
- Queue : Laravel Horizon (Supervisor)
- Tests : Pest PHP

## Setup local développeur

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Références
- Contrats API : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- ERD : `../docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- Règles métier : `../docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- Prompt démarrage : `../docs/PROMPTS_EXECUTION/v2/backend/CC-01_INIT_LARAVEL.md`

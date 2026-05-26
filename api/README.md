# Leopardo RH - Backend API (Laravel 11)

## Stack

- Laravel 11 + PostgreSQL 16
- Auth API : Laravel Sanctum
- Auth web : session manager
- Cache : file
- Queue : sync
- Web : Blade + Alpine.js

Note operations : les livraisons mobiles employee/manager sont distribuees par les workflows GitHub vers Firebase App Distribution apres validation des contrats API et des garde-fous multi-app.

## Surface API actuelle

La branche `main` expose surtout :

- auth employee
- auth super-admin plateforme
- employes
- attendance / pointage
- estimations employee
- invitations
- enrollement biometrique
- kiosks
- cameras

Les contrats produits plus larges presents dans `docs/dossierdeConception/` doivent etre lus comme cible tant qu'un endpoint n'existe pas dans `routes/` et dans son controller associe.
Pour naviguer proprement dans la documentation, commencer par `../docs/README.md`, puis `../docs/REFERENTIEL_PRODUIT/` et `../docs/dossierdeConception/README.md`.

Reference d'alignement :

- `../docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`

## Setup local developpeur (Docker recommande)

```bash
cd api
docker compose up -d
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --path=database/migrations/public --force
docker compose exec app php artisan migrate --path=database/migrations/tenant --force
docker compose exec app php artisan test
```

### Demarrage 1 commande (Windows PowerShell)

```powershell
cd api
.\start-local.ps1 -SeedDemo
```

Options utiles :

- `-Rebuild` : reconstruit l'image Docker
- `-SeedDemo` : injecte les comptes de demonstration
- `-RunTests` : lance `php artisan test` en fin de script

## Verification courante

Le runtime PHP de reference est dans le conteneur `app`.

```bash
cd api
docker compose exec app php -v
docker compose exec app php artisan route:list --except-vendor
docker compose exec app php artisan test
```

## Setup manuel hors Docker

Possible pour developpeur experimente, mais non prioritaire pour l'equipe.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## References

- Vision produit active : `../docs/REFERENTIEL_PRODUIT/APV.md`
- Roadmap active : `../docs/REFERENTIEL_PRODUIT/ROADMAP.md`
- Contrat API cible : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- Alignement doc/code : `../docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
- ERD : `../docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- Regles metier : `../docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- Prompt MVP actif : `../docs/PROMPTS_EXECUTION/v3/MVP-05_DASHBOARD_WEB.md`

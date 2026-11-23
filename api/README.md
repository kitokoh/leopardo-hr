# Leopardo RH - Backend API (Laravel 12)

## Stack

- Laravel 12 + PostgreSQL 16
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
docker compose exec app php artisan leopardo:migrate
docker compose exec app php artisan test
```

### Demarrage 1 commande (Windows PowerShell)

```powershell
cd api
.\scripts\start-local.ps1 -SeedDemo
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
php artisan leopardo:migrate
php artisan serve
```

### Lancer les tests hors Docker (#9855)

La suite a besoin d'un PostgreSQL **avec PostGIS** et d'un `APP_KEY`. Depuis
#9855, elle ne depend plus d'un `CI=true` magique : `tests/TestCase.php`
sonde l'hote configure et ne bascule sur le nom de service `pgsql` que si
celui-ci resout reellement en DNS (c'est-a-dire dans le `docker compose` du
depot). Ailleurs, la valeur de `.env` est conservee telle quelle.

```bash
# 1. Base de test + extensions
createdb leopardo_test
psql -d leopardo_test -c 'CREATE EXTENSION IF NOT EXISTS postgis;'
psql -d leopardo_test -c 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp";'

# 2. Variables (les valeurs par defaut de phpunit.xml suffisent si la base
#    est joignable sur 127.0.0.1:5432 avec leopardo_user/leopardo_pass_test)
export DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=leopardo_test
export DB_USERNAME=leopardo_user DB_PASSWORD=leopardo_pass_test

# 3. Suite
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature/Database/RealMigrationsReplayTest.php
```

Si l'hote configure est injoignable, l'erreur de connexion nomme desormais
cet hote — plus de `could not translate host name "pgsql"` trompeur.

### Mesurer la dette PHPStan reelle (#9856)

`phpstan.neon` inclut une baseline : « niveau max, 0 erreur » ne dit donc
rien du code non couvert par cette liste d'exceptions. Pour mesurer la dette
reelle, sans baseline, par lots (un run unique depasse la memoire d'un
runner modeste) :

```bash
dev-tools/phpstan-debt-report.sh          # rapport lisible
dev-tools/phpstan-debt-report.sh --json   # JSON agrege
```

## References

- Vision produit active : `../docs/REFERENTIEL_PRODUIT/APV.md`
- Roadmap active : `../docs/REFERENTIEL_PRODUIT/ROADMAP.md`
- Contrat API cible : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- Alignement doc/code : `../docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
- ERD : `../docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`
- Regles metier : `../docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md`
- Prompt MVP actif : `../docs/PROMPTS_EXECUTION/v3/MVP-05_DASHBOARD_WEB.md`

## Healthcheck & opérations

- `GET /api/v1/health` : expose `status`, `version` (PROGRAM_VERSION), `uptime_seconds`
  et les checks DB / Redis / storage / queue. Utilisé par le gate de déploiement
  Render (doit matcher PROGRAM_VERSION avec uptime < 900 s).
- CORS : l'origine `http://127.0.0.1:4173` (admin-dashboard E2E Playwright local) est
  whitelistée dans `config/cors.php` — un déploiement est requis pour que Web E2E CI
  redevienne vert après un incident CORS (les E2E tapent la prod depuis le runner).
- Redis : le healthcheck est fail-closed (les jobs échouent si Redis est down) ;
  en cas d'incident, relancer le service Redis Render avant de re-tester.

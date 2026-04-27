# Architecture actuelle - Render / Neon

Date : 2026-04-25

## Statut documentaire

Ce document est la reference operationnelle de l'architecture actuellement observable dans le depot pour :

- le deploiement API/Web,
- le healthcheck applicatif,
- et le chemin CI/CD de production courant.

Il prime sur `Leopardo_RH_Architecture_Deploiement.pdf` pour tout ce qui concerne l'etat reel aujourd'hui.

Le PDF doit etre lu comme :

- une reference de vision,
- un support de cadrage,
- et une projection de cible future,

mais pas comme un runbook exact de l'existant.

## Separation des horizons

### Etat courant

- Hebergement actif : Render pour l'API/Web
- Base de donnees active documentee : PostgreSQL sur Neon
- CD actif : GitHub Actions vers Render deploy hook
- Distribution mobile active : Android staging via Firebase App Distribution
- Healthcheck canonique : `/api/v1/health`

### Cible proche

- Stabilisation beta sur l'infrastructure Render + Neon
- Durcissement documentation / runbooks / backup drill

### Cible future

- Oracle VPS
- Evolution infra plus autonome
- Eventuel enrichissement Redis/queues/workers plus pousses

Ces points futurs ne doivent pas etre interpretes comme deja en service tant qu'ils ne sont pas promus dans `PILOTAGE.md` et les runbooks actifs.

## Etat courant - vue simple

```text
GitHub PR/push
  -> GitHub Actions tests
  -> GitHub Actions deploy-main
  -> Render deploy hook
  -> Render lance l'image Docker Laravel
  -> startup entrypoint + migrations + seeders
  -> /api/v1/health doit retourner {"status":"ok"}
  -> distribution APK staging Android sur Firebase
```

## Deploiement actuel Render

### Ce qui est vraiment deploye

- Le backend Laravel
- Le front web inclus dans l'application Laravel
- Le build est base sur Docker, pas sur un `render.yaml` versionne dans ce depot

### Source technique reelle

- Image : `api/Dockerfile.prod`
- Startup : `api/docker-entrypoint.sh`
- Orchestration CI/CD : `.github/workflows/deploy-main.yml`

### Runtime reel

Le runtime actuel repose sur :

- image Docker `dunglas/frankenphp:php8.4-alpine`
- installation des dependances Composer dans l'image
- execution de `config:cache`, `route:cache`, `view:cache` au demarrage
- bootstrap des tables `migrations`
- execution des migrations `public` puis `shared_tenants`
- seeders de base et demo seed conditionnel
- demarrage final via FrankenPHP

### Flux de deploy canonique

1. Une PR est validee et mergee sur `main`
2. Le workflow `Tests - Leopardo RH` doit finir en succes sur `main`
3. Le workflow `Deploy - Leopardo RH` se declenche
4. Le job `deploy-api` verifie `RENDER_DEPLOY_HOOK_URL`
5. GitHub appelle le deploy hook Render
6. Render reconstruit/redeploie l'application Docker
7. GitHub attend que l'endpoint de healthcheck reponde avec `"status":"ok"`
8. Si le deploy est sain, le workflow distribue l'APK Android staging

## Healthcheck canonique

### Endpoint

L'endpoint de sante canonique est :

- `GET /api/v1/health`

### Pourquoi c'est cette URL

Cette URL est :

- exposee dans `api/routes/api.php`
- implementee par `App\Http\Controllers\Api\V1\HealthController`
- attendue explicitement par `.github/workflows/deploy-main.yml`

### Contrat fonctionnel reel

La reponse inclut notamment :

- `status`
- `version`
- `checks.database`
- `checks.redis`
- `checks.storage`
- `timestamp`

### Regle de sante actuelle

- HTTP `200` si la base repond
- HTTP `503` si la base ne repond plus
- Redis et storage peuvent etre `degraded` sans bloquer le deploy si la base reste joignable

## References actives

Pour operer aujourd'hui, voir d'abord :

1. `PILOTAGE.md`
2. `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
3. `docs/GESTION_PROJET/RENDER_SETUP.md`
4. `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
5. `docs/infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md`

## Runtime API reel

### Image et moteur HTTP

Le runtime de production documente dans le depot repose sur :

- une image Docker construite depuis `api/Dockerfile.prod`
- une base `dunglas/frankenphp:php8.4-alpine`
- un demarrage final via `frankenphp run`

Ce point est important car il remplace toute lecture trop generique du type :

- "Laravel tourne sur Render"

La formulation correcte aujourd'hui est :

- "Laravel est deployee sur Render via une image Docker basee sur FrankenPHP"

### Initialisation au demarrage

Le conteneur execute au startup :

- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- bootstrap des tables `public.migrations` et `shared_tenants.migrations`
- migrations `public`
- migrations `shared_tenants`
- seeders de base

### Consequence documentaire

Toute doc qui decrit un deploy Render sans :

- image Docker,
- startup script,
- migrations au demarrage,

est incomplete par rapport a l'etat reel du depot.

## Stack locale actuelle

### Ce qui est lance localement par defaut

Le `docker-compose` versionne dans `api/docker-compose.yml` lance actuellement :

- `app`
- `pgsql`

### Ce qui n'est pas lance par defaut

Redis n'est pas defini comme service dans le `docker-compose` local versionne a cette date.

### Lecture correcte de la stack locale

La lecture canonique est donc :

- backend local valide en Docker
- PHP 8.4
- PostgreSQL 16
- service principal `app`
- pas de Redis local par defaut dans le compose actif

### Reference equipe

Le runbook local a suivre reste :

- `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`

## Mobile - etat reel

### Point d'entree applicatif

Le point d'entree visible dans le depot est :

- `mobile/lib/main.dart`

La documentation courante ne doit donc pas supposer que les points d'entree :

- `main_dev.dart`
- `main_staging.dart`
- `main_prod.dart`

font partie du chemin principal actuellement maintenu.

### Configuration de l'API

Le chemin actif visible dans les workflows repose surtout sur :

- `dart-define`
- ou `dart-define-from-file`

et non sur des flavors documentes comme mecanisme principal.

### Android - niveau d'automatisation reel

Le pipeline Android est le plus abouti aujourd'hui :

- staging : build APK release + Firebase App Distribution
- prod : build AAB release

Les URLs backend sont injectees dans le workflow au moment du build.

### iOS - etat reel

Le projet iOS existe bien dans `mobile/ios/`, donc le support technique n'est pas inexistant.

En revanche, le depot ne montre pas au meme niveau :

- un workflow iOS actif,
- une distribution TestFlight automatisee,
- ou une soumission App Store equivalente au pipeline Android actuel.

### Consequence documentaire

La documentation doit distinguer clairement :

- Android actif et automatise
- iOS present dans le projet mais non documente ici comme pipeline CI actif equivalent

## Backup / restore reel

### Source canonique

La reference actuelle de sauvegarde et restauration est :

- `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`

Le script actif associe est :

- `scripts/backup_drill.sh`

### Logique de fonctionnement actuelle

Le mecanisme documente dans le depot est un drill de reprise :

1. dump PostgreSQL au format custom
2. chiffrement optionnel avec `age`
3. restauration dans une base scratch isolee
4. verification par comparaison de tables critiques
5. nettoyage de la base scratch

### Ce que la doc ne doit plus sous-entendre

Une documentation qui presente comme reference principale :

- un autre script de backup non versionne ici,
- ou un flux S3 detaille non rebase sur le runbook actif,

doit etre consideree comme obsolete ou secondaire.

### Interpretation operationnelle

Pour l'existant, le runbook de backup/restore et le script de drill priment sur toute annexe PDF plus ancienne.

## Oracle VPS - statut reel

### Qualification correcte

Oracle VPS doit etre lu aujourd'hui comme :

- une cible infra future possible,
- une trajectoire de migration envisageable,
- mais pas une brique operationnelle active ou imminente par defaut.

### Pourquoi

L'etat de pilotage courant pointe explicitement vers :

- beta reelle sur Render,
- deploiement actif sur Render,
- et runbooks alignes sur Render + Neon.

Tant qu'un changement n'est pas promu dans :

- `PILOTAGE.md`
- `CHANGELOG.md`
- `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`

Oracle ne doit pas etre presente comme le socle de production courant.

### Formulation recommandee

La formulation documentaire correcte est :

- "Oracle VPS est une cible conditionnelle de future evolution infra"

et non :

- "Oracle VPS est la production deja preparee"

## Cadrage multitenancy - realite vs ancien MVP

### Point de vigilance

Une partie de la documentation historique simplifie encore le projet comme si le MVP reel etait strictement :

- shared only,
- 2 roles,
- scope RH minimal.

Cette lecture n'est plus suffisante pour decrire l'etat du code sur `main`.

### Ce qui est observable dans la documentation active

Le cadrage courant montre explicitement que le code a depasse l'ancien MVP simplifie :

- mode `schema` deja actif dans le code
- sous-roles manager plus riches
- architecture et design en evolution au-dela du premier perimetre fige

### Regle documentaire

Quand une doc historique contredit la realite du code sur :

- le multitenancy,
- les roles,
- ou le perimetre backend/mobile,

il faut trancher en faveur de :

1. `PILOTAGE.md`
2. `docs/ROADMAP.md`
3. `docs/AUDIT_v2_v3_COMPLIANCE.md`
4. `CHANGELOG.md`

### Consequence pratique

Le PDF infra ne doit donc pas etre utilise pour deduire seul :

- le mode multitenant effectivement livre,
- la profondeur RBAC effectivement livree,
- ou le perimetre exact de ce qui tourne deja sur `main`.

## Pour operer aujourd'hui

Si l'objectif est de deployer, verifier, diagnostiquer ou decider aujourd'hui, il faut ouvrir en priorite :

1. `PILOTAGE.md`
2. `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
3. `docs/GESTION_PROJET/RENDER_SETUP.md`
4. `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`
5. `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
6. `docs/ROADMAP.md`
7. `docs/AUDIT_v2_v3_COMPLIANCE.md`
8. `CHANGELOG.md`

Le PDF d'architecture intervient ensuite comme document de contexte et de projection.

## Impact sur le PDF historique

Les chapitres du PDF qui parlent :

- d'un `render.yaml` comme base du deploy,
- d'un healthcheck different,
- d'une stack locale avec Redis par defaut,
- de flavors Flutter comme chemin principal documente,
- d'un pipeline iOS equivalent a Android deja actif,
- d'un backup/restore decrit autrement que par le runbook courant,
- d'Oracle comme cible quasi acquise ou deja operationnelle,
- d'un MVP multitenant/RBAC plus simple que l'etat reel du code,
- ou d'Oracle comme etat quasiment actif,

doivent desormais etre lus comme obsoletes ou prospectifs tant qu'ils ne sont pas realignes.

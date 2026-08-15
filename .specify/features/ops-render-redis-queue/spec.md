# Feature Specification: Render — Redis + queue worker + scheduler (issue #3774)

**Created**: 2026-08-15

**Status**: Ready for implementation

**Input**: Issue #3774 — `render.yaml` doit activer Redis (plan gratuit), déclarer un worker queue dédié et s'assurer que le scheduler Artisan tourne, avec toutes les variables alignées sur les URLs Render gratuites.

## Contexte technique

`render.yaml` définit déjà (sur main) :
- un web service `gestionemployerbackend` avec `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `REDIS_CLIENT=predis` ;
- un worker `leopardo-queue-worker` (`queue:work redis --queue=notifications,emails,pdf,payroll,default`) ;
- un scheduler `leopardo-scheduler` (`schedule:run` chaque minute).

**Ce qui manque** : le service Redis lui-même n'est pas déclaré dans `render.yaml` — `REDIS_URL`/`REDIS_PASSWORD` sont `sync: false` (saisie manuelle dashboard, actuellement Upstash externe). Aucun `databases` de type `redis` n'existe, donc un déploiement à partir du fichier seul démarre sans file d'attente fonctionnelle (mode sync par défaut).

## User Scenarios & Testing

### US1 — Redis interne Render provisionné par IaC (Priority: P1)
Un déploiement Render créé uniquement à partir de `render.yaml` dispose d'une instance Redis interne et les trois services (web, worker, scheduler) y sont branchés automatiquement.

**Acceptance Scenarios**:
1. **Given** un blueprint Render construit depuis `render.yaml`, **When** les services démarrent, **Then** `REDIS_URL` pointe vers `leopardo-redis` interne (plus aucune saisie manuelle obligatoire).
2. **Given** `QUEUE_CONNECTION=redis` sur le web, **When** un job est dispatché, **Then** il est consommé par `leopardo-queue-worker` avec `--tries=3` et retries espacés.
3. **Given** le scheduler, **When** `schedule:run` tourne chaque minute, **Then** les commandes planifiées s'exécutent sans dépendre d'un cron externe.

### US2 — Aucune régression de configuration (Priority: P1)
Les variables existantes (mail, Firebase, Stripe, DB) restent identiques entre web/worker/scheduler.

**Acceptance Scenarios**:
1. **Given** le diff du PR, **When** comparé à main, **Then** seules les lignes Redis/champ `databases` changent (aucun retrait de `MAIL_*`, `FIREBASE_*`, etc.).
2. **Given** un override externe existant (Upstash via dashboard), **When** `REDIS_URL` est encore `sync: false` sur une instance, **Then** le commentaire documente que l'override dashboard reste possible.

## Requirements

- FR-1: Ajouter `databases` de type `redis` nommé `leopardo-redis` (plan gratuit, `maxmemoryPolicy: allkeys-lru`, `ipAllowList: []`).
- FR-2: Web service : `REDIS_URL` et `REDIS_PASSWORD` branchés sur `leopardo-redis` (`fromDatabase`), avec commentaire documentant l'override externe (Upstash) possible via dashboard.
- FR-3: Worker `leopardo-queue-worker` : `REDIS_URL`/`REDIS_PASSWORD` branchés sur `leopardo-redis` (`fromDatabase`) ; conserver `QUEUE_CONNECTION=redis`, `--tries=3`, timeout 300.
- FR-4: Scheduler `leopardo-scheduler` : `REDIS_URL` branché sur `leopardo-redis` (`fromDatabase`).
- FR-5: `CACHE_STORE=redis` et `SESSION_DRIVER=redis` conservés sur le web.
- FR-6: Aucun changement des autres variables (mail, Stripe, Chargily, Firebase, DB) et aucun changement de code applicatif.
- FR-7: Entrée `CHANGELOG.md` sous `## [Unreleased]` → `### Fixed`/`### Changed` documentant l'activation Redis IaC (Closes #3774).

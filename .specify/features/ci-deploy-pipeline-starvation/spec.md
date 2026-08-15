# Feature Specification: Réparer la famine du pipeline de déploiement main → Render

**Feature Branch**: `fix/ci-deploy-pipeline-starvation`
**Created**: 2026-08-15 | **Status**: Draft
**Constat source**: F8-01 (session QA expert 9, 2026-08-15) — voir `.specify/features/qa-expert9-2026-08-15/findings-registry.md`
**Issues liées (symptômes, déjà ouvertes)**: #2632, #2627, #2812, #2813, #3259

## Problème

La production Render ne reçoit plus aucun déploiement depuis main bien que le pipeline affiche du
vert. Analyse des 50 derniers runs `Tests - Leopardo RH` sur `main` : **48 `cancelled`, 2 en cours,
0 `success`**.

Mécanisme racine (prouvé par les logs, ex. run `31892237640` — 0 job instancié, annulé au push
suivant) :

1. `tests.yml` : `concurrency.group = tests-${{ github.workflow }}-${{ github.ref }}` avec
   `cancel-in-progress: ${{ github.event_name == 'pull_request' }}` (fix #589e41c4).
   Ce fix protège les runs **en cours d'exécution**, mais GitHub ne conserve qu'**un seul run
   pending par groupe de concurrence** : chaque nouveau push sur main annule le run pending
   précédent, indépendamment de `cancel-in-progress`.
2. Cadence actuelle : ~1 merge sur main toutes les 1-3 minutes (agents parallèles) + file de
   runners saturée par les checks PR → aucun run main n'atteint l'état `in_progress`.
3. `deploy-main.yml` n'arme `should_deploy` que si la conclusion du `workflow_run` parent est
   `success` → toujours `false` → job `Deploy API + Web to Render` **skipped sur 100 % des runs**,
   avec un statut de run global « success » trompeur (le skip n'est pas signalé).

Impact utilisateur : tous les correctifs mergés depuis le 2026-08-15 matin sont invisibles en
production (`/api-explorer` 500, vitrine et admin Pages stale).

## User Stories & Testing

### User Story 1 — Un run Tests sur main aboutit toujours (P1)

En tant qu'ops, je veux que le run Tests du **dernier** SHA de main se termine toujours avec une
conclusion réelle (`success`/`failure`), jamais annulé silencieusement, même sous rafale de merges.

**Acceptance Scenarios**:
1. Given 3 pushes sur main à < 5 min d'intervalle, When les runs se stabilisent, Then le run du
   dernier SHA atteint `completed` avec conclusion ≠ `cancelled`.
2. Given un run Tests main `in_progress`, When un nouveau push arrive, Then le run en cours n'est
   pas annulé (comportement déjà acquis par #589e41c4 — ne pas régresser).

### User Story 2 — Le déploiement suit le dernier SHA vert (P1)

En tant qu'ops, je veux que `deploy-main.yml` déploie le SHA main le plus récent dont les checks
requis sont verts, même si des SHAs intermédiaires ont été annulés.

**Acceptance Scenarios**:
1. Given le dernier run Tests main vert, When `deploy-main` se déclenche, Then le job
   `Deploy API + Web to Render` s'exécute (pas `skipped`).
2. Given un SHA dont les checks ont été annulés mais un SHA plus récent vert, When le gate
   évalue, Then il déploie le SHA vert le plus récent et journalise l'écart rattrapé.

### User Story 3 — Un skip de déploiement est visible (P2)

**Acceptance Scenarios**:
1. Given `should_deploy=false`, When le run se termine, Then le Summary du run contient un
   `::warning::` explicite avec la raison (conclusion parente, SHA, horodatage).
2. Given N runs consécutifs skipped, Then un job léger échoue (ou crée/met à jour une issue ops)
   au-delà d'un seuil (ex. 24 h sans déploiement effectif), pour ne plus jamais rester invisible.

## Options techniques (arbitrage dans plan.md)

- **A. Déclencheur `push` direct + attente de checks** : `deploy-main` sur `push: main`, gate par
  polling des check-runs du SHA (timeout borné) ; déploie le dernier SHA vert. Supprime la
  dépendance au `workflow_run` famine-prone.
- **B. Garde l'état « dernier SHA vert »** : un artifact/variable de repo mémorise le dernier SHA
  testé vert ; `deploy-main` (schedule + workflow_run) déploie cet état quand il diffère du SHA
  déployé.
- **C. Merge queue GitHub** : sérialise les merges et garantit un run vert par SHA mergé.
  Robuste mais change le workflow de tous les agents (hors périmètre court terme).

## Non-Goals

- Ne pas affaiblir les gates de sécurité du déploiement (repo/base/branch checks).
- Ne pas déployer un SHA dont les checks requis sont rouges ou inexistants.
- Pas de changement de la stratégie de cache Composer ni des jobs Tests eux-mêmes.

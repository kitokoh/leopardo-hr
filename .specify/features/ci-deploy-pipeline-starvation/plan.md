# Plan — Réparer la famine du pipeline de déploiement main → Render

**Spec**: `./spec.md` | **Date**: 2026-08-15

## Approche retenue : Option A (déclencheur `push` + attente bornée des checks) + US2 (skip visible)

Option A supprime la dépendance famine-prone au `workflow_run` sans changer le workflow des
agents (contrairement à la merge queue, option C). Option B reste un repli si A montre des
limites de quota Actions.

## Étapes techniques

1. **`deploy-main.yml`** : ajouter `on.push.branches: [main]` en plus de `workflow_run` et
   `workflow_dispatch`. Conserver la déduplication par SHA (concurrency `deploy-main-<sha>`).
2. **Gate d'attente des checks** (job `prepare`, step `workflow_gate` existant) : pour un
   événement `push`, au lieu d'exiger que le parent soit déjà `success`, **poller les check-runs
   requis du SHA** (`Tests - Leopardo RH`, `Web CI - Leopardo Admin` si `web_changed`) avec un
   timeout borné (ex. 30 min, intervalle 60 s). Si un check requis est rouge → `should_deploy=false`
   + warning. Si timeout → `should_deploy=false` + warning explicite.
3. **Anti-empilement** : sous rafale de pushes, seul le dernier SHA doit attendre/déployer — le
   concurrency group par SHA + `cancel-in-progress: true` au niveau `deploy-main` annule proprement
   les gates des SHAs intermédiaires (attente = annulable, déploiement = protégé par le
   `deploy-lock` Render côté job).
4. **Skip visible (US2)** : dans `prepare`, quand `should_deploy=false`, écrire
   `::warning::Deploy skipped — <raison>` + une section `$GITHUB_STEP_SUMMARY`.
5. **Garde « dernier déploiement effectif »** : step final qui journalise
   `APP_VERSION` + SHA déployé ; un check hebdo (schedule existant `Launch Observability Smoke`)
   peut alerter si la version prod ≠ main depuis > 24 h (suivi dans #2632).

## Fichiers touchés

- `.github/workflows/deploy-main.yml` (unique fichier modifié)
- `CHANGELOG.md` (entrée `Fixed`)
- `AGENTS.md` (leçon : pending-run cancellation ≠ protégée par `cancel-in-progress:false`)

## Vérification

1. `actionlint` vert sur le workflow modifié.
2. Merge de la PR → observer le run `deploy-main` déclenché par le push : le gate attend les
   checks du SHA puis déploie (ou skippe avec warning explicite si checks rouges).
3. `curl https://gestionemployerbackend.onrender.com/api-explorer` → 200 après déploiement
   effectif (clôt #2632 côté API).

## Risques

- **Quota Actions** : l'attente bornée consomme des minutes runner — mitigé par l'anti-empilement
  (un seul SHA en attente) et l'intervalle 60 s.
- **Double déclenchement** (`push` + `workflow_run` du même SHA) : concurrency par SHA + le gate
  interne existant (« runs pour ce SHA ») rendent les deux chemins idempotents.

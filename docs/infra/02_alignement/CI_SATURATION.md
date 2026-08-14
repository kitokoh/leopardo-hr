# CI — Saturation de la file GitHub Actions (issue #2131)

> Constat : 2026-08-14 — 39+ runs `queued` et 12 `in_progress` en même temps,
> des runs `Tests - Leopardo RH`/`Backend Coverage Gate` annulés en cascade à
> chaque push, le dernier commit de `main` sans checks requis terminés pendant
> 20+ minutes.

## Analyse racine

1. **Annulation en cascade des checks REQUIS sur `main`** (cause principale) :
   `coverage-gate.yml`, `actionlint.yml` et `architecture-check.yml` portent
   les 5 checks requis de la protection de `main`. Ils se déclenchent sur
   chaque push `main` avec `cancel-in-progress: true` et un groupe par
   `ref` (`refs/heads/main`). Chaque nouveau merge annulait donc le run du
   commit précédent → le dernier commit de `main` restait `pending` (check
   requis jamais terminé) pendant toute la durée de la rafale de merges.
2. **Rafales de merges** : merges en batch (7 PR en ~3 min le 2026-08-14)
   sans merge queue → chaque merge relance toute la chaîne
   Tests → Deploy → E2E/OWASP/ZAP.
3. **E2E/OWASP** : groupe par `ref` + `cancel-in-progress: true` → chaque
   nouveau déploiement annulait le run e2e/zap du précédent (travail gaspillé
   + file saturée par les re-déclenchements).

## Merge queue — alternative documentée (critère d'acceptation n°1)

**La merge queue GitHub n'est PAS disponible sur ce plan** (vérifié via
GraphQL le 2026-08-14 : le champ `Repository.mergeQueueEnabled` n'existe pas
sur le schéma — compte personnel plan gratuit). Implémentation impossible
tant que le plan ne change pas.

Alternative appliquée (mêmes effets visés : sérialisation + non-annulation
des runs utiles) :

| Objectif merge queue | Alternative appliquée (#2131) |
|---|---|
| Sérialiser les merges | Discipline de batch : ≤ 2 PR mergées/minute, attendre le vert des 5 checks requis entre chaque merge |
| Valider le ref de merge | Triggers `merge_group` déjà présents sur les 3 workflows requis (#2032) → activation en 1 config quand le plan le permettra |
| Ne pas annuler les runs utiles | `cancel-in-progress: ${{ github.event_name == 'pull_request' }}` sur tous les workflows lourds (tests, coverage, architecture, actionlint, payroll, web-marketing, mobile) — les runs `push main`/`merge_group` ne sont JAMAIS annulés |
| Éviter les re-déclenchements en cascade | E2E/OWASP groupés par SHA du déploiement déclencheur (`workflow_run.head_sha`) au lieu de `ref` |

## Budget de runs par PR (après #2131)

- **PR docs-only** (`docs/**`, `CHANGELOG.md`, `AGENTS.md`) : uniquement les
  3 workflows requis inconditionnels (coverage-gate, actionlint,
  architecture-check) + gardes légères → **≈ 5 runs** (contre 12+ avant).
- **PR backend réelle** : + Tests, Payroll CI, coverage jobs → **≤ 12 runs**.
- **PR front/web** : + web-marketing-ci, lighthouse (filtres `paths:` déjà
  présents) → **≤ 12 runs**.
- **Merge sur `main`** : les runs `push main` des checks requis ne sont plus
  annulés → les 5 checks requis sont verts ≤ 15 min après le merge (constat
  à re-vérifier sur la PR #2131 elle-même).

## Vérification (critère d'acceptation n°4)

- PR docs-only de test : compter les runs lancés (≤ 12) et vérifier qu'aucun
  run `Backend Coverage`/`Tests` n'est annulé par churn.
- PR backend réelle : idem.
- Après merge : vérifier que les 5 checks requis de `main` passent au vert
  ≤ 15 min.

## Fichiers touchés

- `.github/workflows/coverage-gate.yml`, `actionlint.yml`,
  `architecture-check.yml`, `payroll-ci.yml`, `web-marketing-ci.yml`,
  `mobile-apps-ci.yml` — `cancel-in-progress` conditionnel.
- `.github/workflows/e2e-staging.yml`, `owasp-zap.yml` — groupe par SHA.
- Issu : #2131 (Closes). Contexte historique : #1903 (constat), #2032
  (merge_group partiel), #2105/#2132 (path filters sur checks requis).

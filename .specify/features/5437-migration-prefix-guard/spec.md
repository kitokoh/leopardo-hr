# Spec — Garde collisions de préfixes de migrations entre PRs (#5437)

## Problème
4 occurrences de collisions de préfixes tenant (issue #1962 : `2026_08_22_000001`, `2026_08_23_000006` ×4…) ont cassé main/CI. Le garde « Hygiene Guards » ne détecte que les doublons **dans une branche**, jamais **entre branches/PRs ouvertes** : deux agents créent `2026_08_25_000001_*` en parallèle → découverte au merge (rework + rebases).

## Solution
`dev-hub/tools/check-migration-prefixes.mjs` (Node ≥ 18, zéro dépendance) :
1. Liste les PRs ouvertes via l'API GitHub (token `GITHUB_TOKEN`, pagination) + le head de chaque PR.
2. Pour chaque head + main : lit `api/database/migrations/tenant/*.php` (et `shared/` si présent) via l'API contents (ou un `git archive` — à trancher pour la fiabilité) ; extrait le préfixe `YYYY_MM_DD_HHMMSS` de chaque fichier.
3. Compare : si la PR courante introduit un préfixe déjà présent sur main **ou** sur une autre PR ouverte → échec avec `::error` listant préfixe, fichier, PR concurrente.
4. Sortie : `::error title=Collision de préfixe de migration::...` + exit 1 si collision ; sinon exit 0.

Workflow `.github/workflows/migration-prefixes.yml` : trigger `pull_request` (paths `api/database/migrations/**`), job court (< 2 min), permissions `contents: read` + `pull-requests: read`.

## DoD
- Fixture de test (2 PRs fictives même préfixe) → détection + exit 1.
- Cas nominaux : préfixe libre → exit 0 ; collision avec main → exit 1.
- `node --test` unitaire sur l'extraction des préfixes.
- Passer sur main actuel (0 faux positif).
- Note dans `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.

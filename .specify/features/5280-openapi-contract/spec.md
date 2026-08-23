# Feature Specification: Contrat OpenAPI vérifié à chaque PR (issue #5280)

**Feature Branch**: `mod/platform/5280-openapi-contract`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5280 [P1][API] — « Tous les endpoints sont documentés OpenAPI et le contrat est validé en CI ». DoD : « Contrat OpenAPI vérifié à chaque PR ».

## Problème

État mesuré sur `main` (2026-08-23) :

1. **`api/openapi.yaml` est NON-parseable** depuis le merge de `79f08bcb6` (2026-08-17, lots 4+5 #4842) : 4 chemins dupliqués (`/positions/{position}`, `/sites/{site}`, `/schedules/{schedule}`, `/tasks/{task}/comments`) → échec Redocly « duplicated mapping key ». Le lint CI était déclenché par `paths:` et n'a jamais rattrapé cette casse (le dernier commit touchant la spec l'a introduite, les runs suivants ne touchaient ni routes ni spec).
2. **3 erreurs `no-identical-paths`** : `/recruitment/jobs/{id}` vs `/{jobPosting}`, `/recruitment/applicants/{id}` vs `/{applicant}`, `/recruitment/interviews/{id}` vs `/{interview}` — les blocs d'actions (`JobPostingActionController`, param `{id}`) dupliquent les blocs CRUD (param canonique), pourtant la même URL réelle.
3. **10 erreurs `struct`** : `type: [string, "null"]` / `[object, "null"]` / `[integer, "null"]` dans `/approvals/history` — tableaux de types invalides en OpenAPI 3.0 (il faut `nullable: true`).
4. **Miroir/SDK périmés** : `dev-hub/openapi/v1.yaml` + SDK JS/Python dataient d'avant les lots 3-5 → 71 chemins documentés jamais propagés aux intégrateurs externes.
5. **DoD non atteint** : le workflow `OpenAPI CI` n'est déclenché que par `paths:` (routes/spec/SDK) → une PR qui ne touche pas ces chemins n'est jamais contrôlée.

## Décision

1. **Dé-dupliquer les chemins** : fusionner les blocs dupliqués dans leurs blocs canoniques (opérations conservées à l'identique, paramètre de chemin renommé vers le nom canonique) — aligné sur le correctif équivalent `bbc744fb0` (branche #5268, `Refs #5268`).
2. **Normaliser les chemins recruitment** : les opérations des blocs `{id}` sont fusionnées dans `/recruitment/jobs/{jobPosting}`, `/recruitment/applicants/{applicant}`, `/recruitment/interviews/{interview}` (le paramètre de chemin spec devient le nom canonique ; l'URL réelle est inchangée, le nom du paramètre est cosmétique côté spec).
3. **Corriger les nullables** : `type: [X, "null"]` → `type: X` + `nullable: true` (OAS 3.0).
4. **Régénérer miroir + SDK** : `node dev-hub/tools/generate-openapi-sdk.mjs` → `dev-hub/openapi/v1.yaml`, `dev-hub/sdk/MANIFEST.json`, SDK JS/Python à jour.
5. **Garde CI à chaque PR** : retirer le filtre `paths:` du déclencheur `pull_request` de `openapi-ci.yml` (le DoD l'exige) ; conserver le filtre sur `push: main` (leçon #3545 — pression file CI). Les jobs sont légers (lint Redocly ~40 s, coverage ~15 s).
6. **Spec-first** : ce document ; CHANGELOG ; note AGENTS.md.

## User Scenarios & Testing

### User Story 1 — Le contrat est vérifié à chaque PR (Priority: P1)

**Independent Test**: `npx @redocly/cli@1.34.5 lint api/openapi.yaml` → « Woohoo! Your API description is valid » (0 erreur) ; `python3 dev-hub/tools/check-openapi-route-coverage.py --strict-staleness` → `744/744 couvertes, 0 drift, 0 reverse drift` ; `node dev-hub/tools/generate-openapi-sdk.mjs --check` → `Checked 743 OpenAPI operations`.

**Acceptance Scenarios**:

1. **Given** le workflow `openapi-ci.yml`, **When** une PR ne touche ni `api/routes/**` ni la spec, **Then** lint + couverture + sync miroir s'exécutent quand même (déclencheur sans `paths:`).
2. **Given** `api/openapi.yaml`, **When** Redocly le parse, **Then** aucune clé dupliquée, aucun `type: [x, "null"]`, aucun chemin identique à paramètre différent.
3. **Given** le miroir, **When** `generate-openapi-sdk.mjs --check` tourne, **Then** `dev-hub/openapi/v1.yaml` et les SDK sont synchrones avec la spec canonique.
4. **Given** la couverture, **When** le checker tourne avec `--strict-staleness`, **Then** 0 nouvelle route non documentée, 0 drift inverse, allowlist vide.

## Changement

- `api/openapi.yaml` : dé-duplication de 4 chemins, fusion de 3 blocs recruitment `{id}` → canoniques, 10 nullables corrigés (0 erreur Redocly, 762 warnings préexistants).
- `dev-hub/openapi/v1.yaml`, `dev-hub/sdk/MANIFEST.json`, `dev-hub/sdk/javascript/leopardoClient.js`, `dev-hub/sdk/python/leopardo_client.py` : régénérés (71 chemins rattrapés).
- `.github/workflows/openapi-ci.yml` : déclencheur `pull_request` sans `paths:` (DoD #5280) ; commentaire expliquant la règle.

## Hors périmètre

- Les 762 warnings Redocly (style) — préexistants, non bloquants (ne font pas échouer le lint).
- La validation de contrat côté runtime PHP (`OpenApiDocsTest` ne couvre que le rendu /docs — non modifié).
- La refonte des noms de paramètres dans les ROUTES (`{id}` vs `{jobPosting}`) — incohérence historique documentée, sans impact fonctionnel (le coverage checker normalise les paramètres).

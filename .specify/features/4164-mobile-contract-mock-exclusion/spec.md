# Feature Specification: Scoper le garde forbidden-route à l'app (hors mocks API core)

**Feature Branch**: `fix/4164-mobile-contract-mock-exclusion`
**Created**: 2026-08-16 | **Status**: Draft
**Constat source**: session QA 2026-08-16 (run 31932710528 sur main)

## Problème

`Mobile Apps CI - Flutter` (job « Mobile apps split guard ») est rouge sur main :
`platform_admin app must not expose forbidden route /attendance`.

Depuis #4102, `validate-mobile-workflow-contracts.ps1` scanne `leopardo_core`
en plus des racines d'apps. `leopardo_core` contient des chemins d'**endpoints
API** (mock_interceptor.dart `'/attendance'`, offline_sync_service
`'/attendance/check-in'`, …) qui ne sont PAS des routes de navigation. La règle
forbidden-route matche ces chaînes → faux positif permanent.

## User Story

En tant que CI, je veux que le garde forbidden-route ne détecte que les routes
de navigation de l'app elle-même (app.dart / context.push), jamais les chemins
d'API du package partagé, afin que `Mobile Apps CI - Flutter` soit vert sur main
et les PRs — sans perdre le câblage endpoints.

**Acceptance Scenarios**:
1. Given le repo main courant, When on lance `validate-mobile-workflow-contracts.ps1`, Then aucune failure « forbidden route » sur les 5 apps.
2. Given une PR touchant `front/mobile_apps/**`, When CI tourne, Then « Mobile apps split guard » passe.
3. Given une vraie fuite de route de navigation (`context.push('/attendance')` dans le lib de platform_admin), When le garde tourne, Then il échoue toujours.
4. Given le wiring endpoints (`/device-tokens`, `read-all`, …), When le garde tourne, Then les checks endpoints restent satisfaits (scan complet app + core conservé).

## Plan

- `dev-hub/tools/validate-mobile-workflow-contracts.ps1` :
  - `$appLibContent = Get-DartContent $root` — contenu PROPRE de l'app.
  - `$libContent = $appLibContent + core` — conservé pour les checks endpoints.
  - Le check forbiddenRoutes utilise `$appLibContent` (et `$routes` d'app.dart) au lieu de `$libContent`.
- `CHANGELOG.md` : entrée `Fixed`.
- `AGENTS.md` : leçon (garde forbidden-route = navigation UI de l'app, pas les chemins API du package partagé).

## Non-Goals

- Ne pas désactiver le garde forbidden-route (les fuites réelles dans le lib d'une app restent détectées).
- Ne pas renommer/supprimer `mock_interceptor.dart`.
- Ne pas retirer le core du scan endpoints (#4102 reste nécessaire pour /device-tokens).

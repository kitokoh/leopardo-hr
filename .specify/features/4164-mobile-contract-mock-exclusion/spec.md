# Feature Specification: Exclure les mocks API du garde forbidden-route mobile

**Feature Branch**: `fix/4164-mobile-contract-mock-exclusion`
**Created**: 2026-08-16 | **Status**: Draft
**Constat source**: session QA 2026-08-16 (run 31932710528 sur main)

## Problème

`Mobile Apps CI - Flutter` (job « Mobile apps split guard ») est rouge sur main :
`platform_admin app must not expose forbidden route /attendance`.

Depuis #4102, `validate-mobile-workflow-contracts.ps1` scanne `leopardo_core`
en plus des racines d'apps. Le fichier
`front/mobile_apps/leopardo_core/lib/core/api/mock_interceptor.dart` contient
des chemins d'API mockés (`'/attendance'`, `/attendance/check-in`, …). La règle
forbidden-route (les apps tenant ne doivent pas exposer de routes réservées dans
`leopardo_platform_admin`) matche ces chaînes → faux positif permanent.

## User Story

En tant que CI, je veux que le garde forbidden-route ne détecte que les routes
de navigation (app.dart / context.push), jamais les chemins d'API mockés, afin
que `Mobile Apps CI - Flutter` soit vert sur main et les PRs.

**Acceptance Scenarios**:
1. Given le repo main courant, When on lance `validate-mobile-workflow-contracts.ps1`, Then aucune failure « forbidden route » sur les 5 apps.
2. Given une PR touchant `front/mobile_apps/**`, When CI tourne, Then « Mobile apps split guard » passe.
3. Given une vraie fuite de route de navigation (ex. `context.push('/attendance')` ajouté dans platform_admin), When le garde tourne, Then il échoue toujours (pas de perte de couverture).

## Plan

- `dev-hub/tools/validate-mobile-workflow-contracts.ps1` : `Get-DartContent` accepte des patterns d'exclusion (`-ExcludePatterns`), appelé avec `'*mock*.dart'` pour la racine d'app ET pour `leopardo_core` dans le contexte forbidden-route.
- Les checks endpoints (wiring `/device-tokens` etc.) gardent le scan complet (les mocks ne portent pas les endpoints réels ; les services les portent).
- `CHANGELOG.md` : entrée `Fixed`.
- `AGENTS.md` : leçon (garde forbidden-route = routes de navigation, pas chemins API mockés).

## Non-Goals

- Ne pas désactiver le garde forbidden-route.
- Ne pas renommer/supprimer `mock_interceptor.dart`.

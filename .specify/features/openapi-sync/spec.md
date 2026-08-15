# Feature Specification: Alignement openapi.yaml ↔ routes réelles

**Feature Branch**: `fix/2267-openapi-sync`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2267

## Contexte
33 chemins de `api/openapi.yaml` divergent des routes réelles → clients générés 404. Table des écarts dans l'issue #2267 (i18n catalog, exports vs export, bank-exports collection manquante, partner vs growth, SmartAttendance verbes, PATCH/PUT vs POST sur documents/expense-claims/loans).

## User Stories & Testing

### User Story 1 — Un client généré depuis openapi.yaml fonctionne (P1)
Un intégrateur régénère son SDK depuis `/docs/openapi.yaml` : chaque chemin documenté existe et répond avec la shape attendue.

**Independent Test**: script de comparaison chemins-spec ↔ routes-code : 0 écart bloquant ; `openapi-ci.yml` (Redocly) vert.

**Acceptance Scenarios**:
1. Given openapi.yaml, When on extrait les chemins, Then chaque chemin correspond à une route réelle (même préfixe/verbe).
2. Given `/bank-exports` documenté en collection, When GET/POST, Then 200/201 réels.

### User Story 2 — Les endpoints manquants bank-exports existent (P1)
`GET /api/v1/bank-exports` (index paginée) et `POST /api/v1/bank-exports` (création lot) sont implémentés sur `BankExportController` conformément à la spec.

**Acceptance Scenarios**:
1. Given un super-admin authentifié, When GET /bank-exports, Then liste paginée 200.
2. Given un payload valide, When POST /bank-exports, Then 201 (ou 422 si invalide) — cohérent avec la spec.

### User Story 3 — Pas de régression Redocly (P1)
**Acceptance Scenarios**:
1. Given la spec modifiée, When `openapi-ci` tourne, Then lint vert.

## Règle de décision
La **spec suit le code** (les routes existantes sont la source de vérité) SAUF pour `bank-exports` collection où la spec documente un contrat absent → on ajoute le code manquant. Pour les verbes (documents/expense-claims/loans) : aligner la spec sur le code réel.

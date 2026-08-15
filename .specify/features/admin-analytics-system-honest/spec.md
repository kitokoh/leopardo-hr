# Feature Specification: AnalyticsView & SystemView — données réelles, plus de fakes setTimeout

**Feature Branch**: `fix/2271-admin-analytics-system`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2271

## Contexte
`AnalyticsView.vue` et `SystemView.vue` simulent latences et données (`await new Promise(r => setTimeout(r, ...))` + données codées en dur) sans appels API.

## User Stories & Testing

### User Story 1 — Les KPIs analytics proviennent de l'API (P1)
**Acceptance Scenarios**:
1. Given la vue Analytics ouverte, When chargement, Then données issues de `GET /api/v1/admin/dashboard/stats` (+ activities, alerts) — vérifiable en network.
2. Given une erreur API, When chargement, Then état erreur clair.

### User Story 2 — La vue Système est honnête (P1)
**Acceptance Scenarios**:
1. Given une brique sans endpoint backend, When affichage, Then état « non disponible » explicite (aucune donnée factice).
2. Given un endpoint réel d'observabilité plateforme, When chargement, Then données réelles.

### User Story 3 — Aucun faux setTimeout de données (P1)
**Acceptance Scenarios**:
1. Given le code, When grep setTimeout dans les 2 vues, Then plus aucun (ou uniquement UI polling documenté, jamais pour fabriquer des données).

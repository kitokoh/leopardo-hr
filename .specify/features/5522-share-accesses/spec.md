# Feature Specification: Audit des accès au portail client — consultation RGPD (Closes #5522)

**Feature Branch**: `fix/5522-share-accesses-audit`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5522 (P2, security, web, backend)
**Spec**: `.specify/features/5522-share-accesses/spec.md`
**Anti-collision**: chaînée sur #5429 (traçabilité) → #5520 (AuditLog unifié) ; UI autonome (le module Comptabilité complet #5534 intégrera cette page dans sa section document).

## Contexte

La traçabilité des accès portail existe (AuditLog `accounting.share.info`/`accounting.share.download`, #5495/#5429, unifiés #5520) mais aucune interface ni endpoint dédié ne permet à un manager/principal de consulter « qui a consulté/téléchargé quel document partagé, quand » — exigence RGPD (loi 18-07), indispensable pour répondre à un incident.

## User Stories & Testing

### US-1 — Endpoint backend (P1)

1. Given un document partagé consulté + téléchargé via le portail, When GET `/api/v1/accounting/documents/shared/{document}/accesses` en `principal`, Then 200 + liste paginée (action, module, request_id, ip_address, user_agent, created_at), ordre décroissant.
2. Given rôle `comptable`, Then 200 (même liste).
3. Given rôle `employee`, Then 403.
4. Given document inconnu OU cross-tenant, Then 404 (fail-closed, scope BelongsToCompany).
5. Given non authentifié, Then 401.
6. Given > 25 accès, Then pagination (`per_page`, `meta.total/last_page`).

### US-2 — Page web « Historique des accès » (P1)

1. Given la page `/accounting/share-accesses`, Then sélecteur de documents (GET `/accounting/documents`) + état « sélectionnez un document ».
2. Given document sélectionné avec accès, Then tableau (date localisée, action localisée consultation/téléchargement, IP, user-agent, request_id) + total + pagination.
3. Given document sans accès, Then état vide explicite.
4. Given échec réseau, Then message d'erreur + bouton réessayer.
5. Given locale ar/tr/en, Then libellés localisés (i18n ×4, namespace `shareAccesses.*`).

## Requirements

- FR-1 : contrôleur `ShareAccessController` (RBAC principal/comptable, isolation tenant 404, pagination).
- FR-2 : route `/documents/shared/{document}/accesses` dans le groupe RBAC accounting.
- FR-3 : `AuditLogResource` expose `user_agent` (additif).
- FR-4 : Feature test `ShareAccessAuditTest` (RBAC 403, isolation 404, pagination, 401).
- FR-5 : page Next.js `(dashboard)/accounting/share-accesses` + Jest tests.
- FR-6 : OpenAPI (opération + schéma), miroir `dev-hub/openapi/v1.yaml` + SDK JS/Python synchronisés.
- FR-7 : i18n ×4 `shareAccesses.*` (21+1 clés), validateur vert.
- FR-8 : CHANGELOG + cette spec.

## DoD

- [ ] Endpoint + UI livrés
- [ ] Tests Feature + Jest
- [ ] i18n ×4
- [ ] OpenAPI + SDK synchronisés
- [ ] CHANGELOG + spec

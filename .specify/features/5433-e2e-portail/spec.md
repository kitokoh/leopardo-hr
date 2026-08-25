# Feature Specification: E2E portail client documents partagés (Closes #5433)

**Feature Branch**: `mod/accounting/5433-e2e-portail`
**Issue**: #5433 (P2, QA/tests — portail #5225/#5233)
**Created**: 2026-08-25
**Status**: Implementation

## Contexte

Le portail client (backend #5357, web #5403) est mergé. Les routes publiques
`documents/shared/{token}` + `/download` avaient **disparu de main** (dégât des
merges #5495/#5377) — restaurées dans cette PR (pattern cabinet.php, throttle
60/min). Le parcours de bout en bout n'était pas testé en CI de façon complète.

## User Stories

### US1 — Parcours complet backend (P1)

**Independent Test** : `tests/Feature/Accounting/PortalJourneyE2ETest.php`

**Scenarios** :
- Given un document + contact réels, When `SendDocumentEmail` est exécuté (transport fake), Then un partage est créé avec token 64 chars et l'email contient le lien.
- Given le token du partage, When `GET /api/v1/accounting/documents/shared/{token}`, Then 200 avec méta limitée (number/type/status/issue_date/currency/total_ttc/expires_at — sans données sensibles).
- Given le token, When `GET .../download`, Then 200 avec PDF réel (`%PDF`, content-type application/pdf).
- Given un partage expiré, When GET info/download, Then 404.
- Given un token inconnu, When GET, Then 404.
- Given un partage du tenant B, When accès depuis le contexte tenant A, Then 404 (aucune fuite cross-tenant).

### US2 — Traçabilité RGPD (#5429, couverture dans cette PR)

**Scenarios** :
- Given des accès info + download sur un partage, Then 1 entrée `AuditLog` `accounting.share.info` et 1 `accounting.share.download` (tenant de la compagnie, user_id null — accès non authentifié).

## Requirements

- FR-001 — Les routes publiques `GET /accounting/documents/shared/{token}` et `GET /accounting/documents/shared/{token}/download` DOIVENT exister hors groupe auth, avec `throttle:60,1`.
- FR-002 — Le parcours complet DOIT être couvert par un Feature test de bout en bout (pattern CriticalFunnelPayrollE2ETest #5285), y compris expiration, token inconnu et cross-tenant 404.
- FR-003 — Les accès info/download DOIVENT être tracés dans `AuditLog` (assertions dans le test).

## Success Criteria

- SC-001 — `PortalJourneyE2ETest` vert (parcours complet + AuditLog + RGPD cross-tenant).
- SC-002 — Non-régression des tests existants du partage (`DocumentShareEmailTest`, `PublicDocumentShareResolutionTest`, `PurgeExpiredSharesTest`).
- SC-003 — PHPStan strict + Pint verts ; CHANGELOG ; spec `.specify`.

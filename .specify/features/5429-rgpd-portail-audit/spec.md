# Feature Specification: Audit RGPD portail client (Closes #5429)

**Feature Branch**: `security/5429-rgpd-portail-audit`
**Issue**: #5429 (P2, security/compliance — portail #5225/#5233)
**Created**: 2026-08-25
**Status**: Implementation

## Contexte

Le portail client (`/documents/shared/{token}`) expose des URL tokenisées.
Deux trous RGPD identifiés :
1. **Traçabilité** — qui consulte/télécharge quel document partagé : **DÉJÀ RÉGLÉ sur main** (`PublicDocumentShareController::auditAccess()`, actions `accounting.share.info`/`accounting.share.download`, livré via #5495). Couverture test ajoutée dans le lot jumeau #5433 (`PortalJourneyE2ETest` : assertions AuditLog).
2. **Referrer-Policy** — le token dans l'URL peut fuiter via l'en-tête `Referer` vers des hôtes tiers (polices, analytics, images). **Reste à faire : poser `Referrer-Policy: no-referrer` sur `/documents/shared/*`** côté front/web.

## User Stories

### US1 — Referrer-Policy no-referrer (P1)

**Independent Test** : `front/web/e2e/portal-document.spec.ts` (test existant `portail : Referrer-Policy no-referrer sur les URL tokenisées (#5429)`)

**Scenarios** :
- Given la page `/documents/shared/{token}`, When un client la charge, Then la réponse HTTP porte `Referrer-Policy: no-referrer` (le token ne part jamais en `Referer`).
- Given la même page, Then `X-Content-Type-Options: nosniff` est présent (cohérence avec le catch-all).
- Given une page hors portail (ex. `/`), Then `Referrer-Policy: strict-origin-when-cross-origin` reste appliquée (non-régression `security-headers.spec.ts`).

### US2 — Traçabilité (déjà livrée, couverte dans #5433)

**Scenarios** :
- Given un accès info + download, Then 2 entrées `AuditLog` (actions `accounting.share.info`/`accounting.share.download`) — vérifié par `PortalJourneyE2ETest` (lot #5433).

## Requirements

- FR-001 — La route `/documents/shared/:path*` DOIT porter `Referrer-Policy: no-referrer` (headers Next.js, après la règle catch-all pour priorité d'override).
- FR-002 — La route DOIT conserver `X-Content-Type-Options: nosniff`.
- FR-003 — Les autres routes ne DOIVENT PAS changer de policy (strict-origin-when-cross-origin conservé).

## Success Criteria

- SC-001 — Test e2e `portal-document.spec.ts` vert (no-referrer sur la page portail).
- SC-002 — Test e2e `security-headers.spec.ts` vert (non-régression catch-all).
- SC-003 — Traçabilité AuditLog couverte par `PortalJourneyE2ETest` (lot #5433) ; CHANGELOG ; spec `.specify`.

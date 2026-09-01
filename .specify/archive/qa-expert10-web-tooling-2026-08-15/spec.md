# Feature Specification: Web live & tooling — headers de sécurité, Postman (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Tests live du 2026-08-15 (vitrine Vercel, admin Cloudflare Pages, API Render) + collection Postman.

## User Scenarios & Testing

### US1 — Headers de sécurité appliqués (Priority: P3) — Issue #3601
CSP enforce (plan de sortie du report-only + retrait unsafe-inline via nonces) ; suppression `x-powered-by`.

**Acceptance Scenarios**:
1. **Given** la vitrine, **When** on lit les headers, **Then** `Content-Security-Policy` (pas seulement Report-Only).
2. **Given** l'API, **When** GET /health, **Then** pas de `x-powered-by`.

### US2 — Collection Postman dédupliquée (Priority: P3) — Issue #3602
Une requête canonique par endpoint ; garde anti-doublon.

## Requirements
- FR-1: CSP enforce vitrine + admin (étape 1 : rapport → politique stricte nonces)
- FR-2: expose_php Off / retrait header au niveau serveur
- FR-3: dédup login ×2 (Public vs Auth) + script de validation méthode+URL uniques

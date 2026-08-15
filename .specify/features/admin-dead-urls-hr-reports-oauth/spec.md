# Feature Specification: Admin SPA — URLs mortes /v1/hr-reports et /v1/platform/marketing/oauth-config

**Feature Branch**: `fix/2237-admin-dead-urls`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2237 (QA wave 2026-08-14, T013 — spec `qa-hardening-wave-2026-08-14` US2)

## Contexte
Le backend sert les routes cockpit sous `/admin/*` (groupe `prefix('admin')`, auth `super_admin_api`). Deux vues admin appellent encore `/v1/...` :
- `ExportsView.vue:172` → `GET /v1/hr-reports` (seul `/admin/hr-reports` existe) → 404.
- `MarketingOAuthView.vue:118` → `PUT /v1/platform/marketing/oauth-config` (seul `/admin/platform/marketing/oauth-config` existe) → 404.

Le client axios admin normalise `/v1/` → `/api/v1/` mais ne connaît pas les routes `/admin/` : il faut corriger les chemins côté frontend (les vues fonctionnelles utilisent déjà `/admin/...`, ex. `TaxSlabEditor.vue`, `dashboard.js`).

## User Stories & Testing

### User Story 1 — La vue Exports génère un rapport HR sans 404 (P1)
**Acceptance Scenarios**:
1. Given la vue Exports, When on génère un rapport HR, Then l'appel passe par `/admin/hr-reports` et le résultat s'affiche (ou une erreur explicite).
2. Given la vue Exports, When vérification grep `v1/hr-reports`, Then aucune occurrence résiduelle.

### User Story 2 — La vue Marketing OAuth sauvegarde une config sans 404 (P1)
**Acceptance Scenarios**:
1. Given la vue Marketing OAuth, When on sauvegarde une config LinkedIn/Facebook/X, Then l'appel passe par `/admin/platform/marketing/oauth-config` (PUT) et un feedback s'affiche.
2. Given la vue Marketing OAuth, When vérification grep `v1/platform/marketing/oauth-config`, Then aucune occurrence résiduelle.

## Requirements

### Functional Requirements
- **FR-001**: `ExportsView.vue` DOIT appeler `GET /admin/hr-reports`.
- **FR-002**: `MarketingOAuthView.vue` DOIT appeler `PUT /admin/platform/marketing/oauth-config`.
- **FR-003**: Aucun changement backend (les routes existent déjà).

## Success Criteria
- **SC-001**: `npm run lint` et `npm run build` verts dans `front/admin-dashboard`.
- **SC-002**: Aucun appel `/v1/hr-reports` ni `/v1/platform/marketing/oauth-config` dans `src/`.

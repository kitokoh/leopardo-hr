# Feature Specification: Correction chemins API ChatView + MarketingOAuthView

**Feature Branch**: `fix/2270-admin-chat-oauth-paths`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2270

## Contexte
- `ChatView.vue:129,139` appelle `/v1/ai/conversations` (tenant) au lieu de `/v1/admin/ai/conversations` (super-admin, existe en repo).
- `MarketingOAuthView.vue:118` appelle `/v1/platform/marketing/oauth-config` au lieu de `/v1/admin/platform/marketing/oauth-config`.

## User Stories & Testing

### User Story 1 — Le super-admin lit ses conversations IA (P1)
**Acceptance Scenarios**:
1. Given un token super-admin, When ChatView charge, Then GET /api/v1/admin/ai/conversations → 200 (via proxy normalizeApiPath).
2. Given une conversation, When ouverture, Then messages via /admin/ai/conversations/{id}/messages → 200.

### User Story 2 — La config OAuth marketing se sauvegarde (P1)
**Acceptance Scenarios**:
1. Given le formulaire rempli, When Enregistrer, Then PUT /api/v1/admin/platform/marketing/oauth-config → 200.
2. Given un payload invalide, Then erreur affichée proprement (pas de 404).

## Notes
- Vérifier le shape attendu par les contrôleurs backend (`PlatformAdminAiConversationController`, `PlatformMarketingOAuthConfigController`).
- Les routes `/admin/*` existent dans le repo (déploiement nécessaire pour la prod — hors périmètre code).

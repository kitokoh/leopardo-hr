# Feature Specification: Google OAuth vitrine — passer par le proxy same-origin

**Feature Branch**: `fix/2277-web-google-oauth-proxy`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2277

## Contexte
`front/web/src/app/auth/login/page.tsx` (L135-141, L484) : le bouton Google utilise `getApiBaseUrl()` → URL directe Render, contournant le proxy Next `/api/v1/auth/google`. Le cookie de session n'est pas posé sur le domaine vitrine → session perdue au retour.

## User Stories & Testing

### User Story 1 — Le login Google passe par le proxy (P1)
**Acceptance Scenarios**:
1. Given la page login, When clic « Continuer avec Google », Then href = `/api/v1/auth/google` (same-origin).
2. Given le proxy, When GET /api/v1/auth/google via Vercel, Then redirection vers Google (302) — pas d'URL Render directe dans le DOM.

### User Story 2 — Le callback reste cohérent (P1)
**Acceptance Scenarios**:
1. Given le retour Google, When callback via proxy, Then le cookie est posé sur le domaine vitrine et la session existe (GET /auth/me OK).

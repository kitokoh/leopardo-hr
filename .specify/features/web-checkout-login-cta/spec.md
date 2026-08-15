# Feature Specification: Web — CTA checkout/success vers la route web /auth/login

**Feature Branch**: `fix/2234-web-checkout-login-cta`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2234 (QA wave 2026-08-14, T010 — `.specify/features/qa-hardening-wave-2026-08-14/`)

## Problème

`checkout/success/page.tsx` (l.67, 85, 321) : les CTA pointent vers `${DEFAULT_BACKEND_API_URL}/auth/login` — URL API Laravel (JSON/404). La route web du portail client est `/auth/login`.

## User Stories & Testing

### User Story 1 — Les CTA mènent au login web (P1)
**Acceptance Scenarios**:
1. Given la page /checkout/success, When clic sur « Accéder au dashboard », Then navigation vers `/auth/login` (route web Next.js), pas d'URL API.
2. Given la carte « Invitez votre équipe », When clic, Then navigation vers `/auth/login`.
3. Given le CTA principal « Accéder à mon espace », When clic, Then navigation interne vers `/auth/login` (pas de nouvel onglet).

## Plan technique
1. Remplacer les 3 href par `/auth/login` ; `external` → `false` ; CTA principal : `<a target="_blank">` → `<Link>` interne.
2. Lint + build web.
3. CHANGELOG + PR `fix/2234-...` `Closes #2234`.

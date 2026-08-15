# Feature Specification: Réparation GET /company/qr-onboarding (500 — lookup entreprise sous search_path)

**Feature Branch**: `fix/2266-qr-onboarding-lookup`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2266

## Contexte
`GET /api/v1/company/qr-onboarding` → 500 sur prod. `OnboardingQrController::findCompanyFromPublicSchema()` bascule `search_path` sur `public` puis fait `Company::query()->findOrFail()` (Eloquent). Sur PostgreSQL partagé ce pattern échoue ; le pattern canonique validé est `App\Support\PlatformCompanyLookup::findOrFail()` (query builder + `public.companies` qualifié + `newFromBuilder`), déjà utilisé par les endpoints plateforme (AGENTS.md v4.16.236).

## User Stories & Testing

### User Story 1 — Le manager génère un QR d'onboarding entreprise (P1)
Un manager `principal`/`rh` ouvre l'écran d'onboarding QR : la page charge le profil QR de son entreprise sans erreur.

**Independent Test**: Feature test : `GET /api/v1/company/qr-onboarding` avec un manager principal → 200 + payload (qr_token, entreprise) ; non-manager → 403 ; aucun 500.

**Acceptance Scenarios**:
1. Given un manager principal authentifié, When GET /company/qr-onboarding, Then 200 avec le payload QR attendu.
2. Given un employé non-manager, When le même appel, Then 403 (jamais 500).
3. Given PostgreSQL partagé, When le lookup, Then aucune erreur SQL (42P01/42703).

### User Story 2 — Pas de duplication de pattern (P2)
La logique de lookup public.companies n'existe qu'à un seul endroit.

**Acceptance Scenarios**:
1. Given le code réparé, When on grep `findCompanyFromPublicSchema`, Then plus aucune implémentation dupliquée dans `OnboardingQrController` (usage de `PlatformCompanyLookup`).

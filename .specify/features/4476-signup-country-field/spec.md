# Feature Specification: Champ pays sur le formulaire d'essai guidé (Closes #4476)

**Feature Branch**: `fix/4476-signup-country-field`
**Created**: 2026-08-16 | **Status**: Implemented
**Issue**: #4476 (P1, web, onboarding)

## Contexte

`POST /api/v1/trial/signup` exige `country` (`required|size:2|SupportedCountry`,
décision MULTI-PAYS #1867 — plus de fallback DZ silencieux). Le formulaire
vitrine /signup ne collectait jamais le pays → 422 systématique →
`signupError: VALIDATION_ERROR` → chaque inscription retombait en lead-only
(« notre équipe vous contacte sous 24h »), sans OTP ni provisionnement.

## User Stories & Testing

### User Story 1 — L'essai guidé se déclenche depuis la vitrine (P1)

En tant que prospect, je veux choisir mon pays à l'inscription pour recevoir
le code OTP et accéder à mon environnement d'essai.

**Acceptance Scenarios**:
1. Given le formulaire /signup, When je le consulte, Then un select pays est
   affiché, pré-rempli depuis ma locale (fr→FR, tr→TR, ar→DZ, en→US).
2. Given ma soumission, When je choisis un pays supporté, Then le payload
   `/api/forms/signup` contient `country` et le backend reçoit un 2xx (OTP).
3. Given l'API, When `country` manque, Then l'UI fournit toujours un défaut
   locale-safe (jamais de 422 silencieux).

## Requirements

- **FR-001**: select pays dans `SignupForm.tsx` (labels ×4 locales via
  `CURRENCY_OPTIONS`, clés `signup.labelCountry`/`countryPlaceholder`).
- **FR-002**: schéma zod `country` (`FR|DZ|MA|TN|TR|US|CA`) + message
  `signup.validation.countryRequired` ×4 locales.
- **FR-003**: `submitSignupForm` envoie `country` (défaut `localeDefaultCountry`).
- **FR-004**: clés i18n propagées (validate-and-sync vert).

## Success Criteria

- **SC-001**: tsc strict 0 erreur ; jest SignupForm 20/20 ; eslint 0.
- **SC-002**: `POST /api/forms/signup` complet → `provisioned:true` (OTP).
- **SC-003**: validate.js OK (4 locales).

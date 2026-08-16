# Feature Specification: Trial signup — messages d'erreur localisés (Closes #4395)

**Feature Branch**: `fix/4395-trial-signup-i18n`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4395 (P1, api, i18n — funnel onboarding EN/TR/AR)

## Contexte

Le funnel trial public (`POST /api/v1/trial/signup` → `POST /api/v1/trial/verify`)
renvoie 6 messages 100 % FR via l'Action applicative `VerifyTrialSignup`
(champs `message` servis tels quels par `SelfServiceTrialController`). Les
tenants EN/TR/AR reçoivent des erreurs françaises — constat qa-expert-360,
#4395. Le fix #3237 avait couvert le contrôleur mais pas l'Action.

## User Stories & Testing

### User Story 1 — Un utilisateur EN reçoit des erreurs anglaises (P1)

**Acceptance Scenarios**:
1. Given un signup puis un OTP erroné, When verify avec `Accept-Language: en`,
   Then `message` = « Invalid or expired verification code. »
2. Given une demande déjà claimée, When verify avec `Accept-Language: en`,
   Then `message` = « This trial request has already been processed. » (409)
3. Given `Accept-Language: fr|en|tr|ar`, When verify OTP erroné,
   Then `message` suit la locale (4 assertions).

## Requirements

### Functional Requirements

- **FR-001**: `VerifyTrialSignup` localise les 6 messages via `__('errors.*')`
  (clés `ALREADY_PROCESSED`, `INVALID_OR_EXPIRED_CODE`, `EMAIL_ALREADY_REGISTERED`,
  `INVALID_COUNTRY`, `NO_PLAN_AVAILABLE`, `PROVISIONING_FAILED`).
- **FR-002**: les 6 clés existent dans `api/lang/{fr,en,ar,tr}/errors.php`
  (fichier maintenu à la main, hors sync i18n — pattern #3237).
- **FR-003**: test de régression `TrialSignupLocalizationTest` (3 scénarios).

## Success Criteria

- **SC-001**: `TrialSignupLocalizationTest` vert (3 tests, 4 locales).
- **SC-002**: zéro littéral FR dans `VerifyTrialSignup` (grep de contrôle).
- **SC-003**: PHPStan strict vert, Pint propre.

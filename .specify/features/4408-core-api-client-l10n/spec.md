# Feature Specification: ApiClient core — messages d'erreur localisés (Closes #4408, volet core)

**Feature Branch**: `fix/4408-core-api-client-l10n`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4408 (P2, mobile, i18n — EN/TR/AR exposés au français)

## Contexte

`leopardo_core/lib/core/api/api_client.dart` construisait ses messages de
repli en FR codé en dur (« Impossible de se connecter au serveur », « Fonction
bientôt disponible », « Compte suspendu », ...) → affichés verbatim dans les
4 locales de toutes les apps. (Le volet platform_admin ~40 chaînes + widgets
core sync_status_banner/QR card reste traité séparément.)

## User Stories & Testing

### User Story 1 — Une erreur réseau s'affiche dans la langue de l'appareil (P1)

**Acceptance Scenarios**:
1. Given un timeout de connexion sur un appareil TR, When erreur, Then
   « Bağlantı zaman aşımı » (jamais « Delai de connexion depasse »).
2. Given un 403 suspension sur un appareil AR, When erreur, Then message AR.
3. Given un payload serveur avec `localized_message`, Then ce message prime.
4. Given un code inconnu, Then repli FR (jamais de crash).

## Requirements

### Functional Requirements

- **FR-001**: `core/i18n/error_messages.dart` — catalogue `code → {fr,en,tr,ar}`
  + `localizedErrorCode(code, [locale])` (locale appareil par défaut).
- **FR-002**: `_handleError` et le fallback download utilisent le catalogue.
- **FR-003**: `localized_message` serveur toujours prioritaire.
- **FR-004**: test unitaire catalogue (8 codes × 4 locales + replis).

## Success Criteria

- **SC-001**: `error_messages_test.dart` vert ; `flutter analyze` core vert.
- **SC-002**: 0 littéral FR dans api_client (grep de contrôle).

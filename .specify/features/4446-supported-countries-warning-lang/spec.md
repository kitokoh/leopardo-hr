# Feature Specification: Registre pays — warning/labels cohérents (Closes #4446)

**Feature Branch**: `fix/4446-supported-countries-warning-lang`
**Created**: 2026-08-16 | **Status**: Implemented
**Issue**: #4446 (P3, api, i18n)

## Contexte

`GET /api/v1/supported-countries` servait `compliance.warning` dans un mélange
FR/EN : pilot/placeholder → texte brut EN des fixtures ; `unknown` (GB/US/CA) →
littéral FR localisé. Les labels des pays `language: en` étaient francisés
(`Etats-Unis`, `Royaume-Uni`). `warning_localized` était déjà correct.

## User Stories & Testing

### User Story 1 — Le registre public parle une langue cohérente (P3)

En tant qu'intégrateur multi-pays, je veux un `warning` brut dans la langue des
règles (EN) et des labels alignés sur la langue déclarée du pays.

**Acceptance Scenarios**:
1. Given le registre public, When je consulte `compliance.warning` d'un pays
   `unknown`, Then le texte est en EN (comme pilot/placeholder), jamais FR.
2. Given les pays `language: en`, When je consulte `label`, Then US → "United
   States" et GB → "United Kingdom".
3. Given une requête en FR, When je consulte `warning_localized`, Then la
   localisation FR reste servie (champ distinct).

## Requirements

- **FR-001**: `warning` du niveau `unknown` servi via
  `__('payroll.compliance_warning_unknown', [], 'en')` (brut EN, cohérent avec
  `$rules->complianceWarning()`).
- **FR-002**: labels `CountryDefaults` alignés sur `language` (US/GB en anglais).
- **FR-003**: tests Feature : aucun warning brut FR + labels EN pour US/GB.

## Success Criteria

- **SC-001**: `SupportedCountryControllerTest` vert (4 tests existants + 2 nouveaux).
- **SC-002**: `php -l` 0 erreur sur les fichiers touchés.
- **SC-003**: le contrat public reste intact (aucune clé supprimée).

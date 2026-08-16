# Feature Specification: SeedDefaultSteps — clés step_key/title (Closes #4188)

**Feature Branch**: `fix/4188-seed-default-steps-keys`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4188 (P1, api, onboarding)

## Contexte

`SeedDefaultSteps` insérait les étapes d'onboarding avec les clés `key`/`label`
alors que `OnboardingStep::$fillable` ne contient ni l'un ni l'autre (colonnes
réelles : `step_key`, `title`) → chaque étape était insérée avec `step_key`/`title`
NULL (perte silencieuse depuis le durcissement fillable #3677). La dédup lisait
`pluck('key')` (attribut inexistant → `[null]`) → un re-provisioning dupliquait
les étapes.

## User Stories & Testing

### User Story 1 — L'onboarding d'un nouveau tenant affiche ses étapes (P1)

En tant que manager découvrant Leopardo, je veux voir les 6 étapes de
configuration par défaut avec leur titre.

**Acceptance Scenarios**:
1. Given un tenant provisionné, When SeedDefaultSteps s'exécute, Then 6 étapes
   existent avec `step_key` et `title` non NULL.
2. Given un second appel, When re-seed, Then aucune ligne supplémentaire (idempotent).
3. Given une étape complétée manuellement, When re-seed, Then pas de doublon
   pour cette étape.

## Requirements

- **FR-001**: `OnboardingStep::create` DOIT mapper `key`→`step_key`, `label`→`title`.
- **FR-002**: la dédup DOIT lire `pluck('step_key')`.
- **FR-003**: test de régression (3 scénarios).

## Success Criteria

- **SC-001**: `SeedDefaultStepsTest` vert.
- **SC-002**: PHPStan strict vert, Pint propre.

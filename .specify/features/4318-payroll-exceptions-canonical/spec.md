# Feature Specification: API — exceptions paie dédupliquées, throwers/catchers alignés (issue #4318)

**Feature Branch**: `fix/4318-payroll-exceptions-canonical`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA — 3 exceptions dupliquées (legacy `App\Exceptions` vs module
`App\Modules\Payroll\Domain\Exceptions`). `PayrollService` lançait les versions legacy
tandis que `PayrollRunController`/`PayrollClosingService` catchaient les versions module
→ **les catchs du contrôleur ne s'appliquaient jamais** aux exceptions réellement
lancées (erreurs 422 non normalisées, code 500 potentiel).

## Problème

- `PayrollAlreadyValidatedException` : legacy lancée par PayrollService (3 sites),
  module catchée par PayrollRunController (2 sites) + PayrollClosingService.
- `PayrollPeriodConflictException` : legacy lancée par PayrollService (2 sites),
  version module inutilisée.
- `SalaryAdvanceAmountExceedsSalaryException` : legacy inutilisée (code documenté
  `ADVANCE_EXCEEDS_SALARY` dans docs/api/ERROR_CODES.md).

## Décision

- **Classe canonique = version module** (`App\Modules\Payroll\Domain\Exceptions`) :
  constructeurs compatibles (message nullable pour AlreadyValidated, `(month, year)`
  pour PeriodConflict), mêmes errorCodes.
- `PayrollService` + `PayrollServiceTest` basculent sur les classes module.
- Suppression des 3 classes legacy.
- `SalaryAdvanceAmountExceedsSalaryException` (module) conservée : code documenté.

## User Scenarios & Testing

### User Story 1 — Le contrôleur catch les exceptions réellement lancées (Priority: P3)

**Independent Test**: `php -l` des fichiers touchés ; `PayrollServiceTest` (asserts
`expectException` sur les classes canoniques — garde anti-régression) ;
`PayrollClosingTest` ; grep zéro référence legacy.

**Acceptance Scenarios**:

1. **Given** un run de paie déjà validé, **When** `PayrollService` est appelé,
   **Then** l'exception lancée est la classe module (catchable par PayrollRunController).
2. **Given** la base de code, **When** on grep `App\Exceptions\Payroll*`,
   **Then** zéro occurrence.
3. **Given** la CI, **When** PHPStan strict s'exécute, **Then** 0 erreur (classes supprimées
   non référencées).

## Edge Cases

- `PayrollClosingService` construit l'exception avec un message : la classe module
  accepte `?string $message` ✓.
- Le code `ADVANCE_EXCEEDS_SALARY` reste documenté (classe module conservée).

# Feature Specification: Payroll run — gate `acknowledge_placeholder` sur le chemin de calcul

**Feature Branch**: `fix/2332-run-placeholder-gate`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2332

## Contexte
La garde 422 `acknowledge_placeholder=true` pour les pays placeholder (CF/TD/GQ/TG/BJ/NE) existe sur les contrôleurs de simulation (`CotisationSimulationController`, `PayrollSimulationController` — #1872) mais PAS sur le chemin de calcul d'un run réel : `PayrollRunController@calculate` → `PayrollCalculator::calculateRun` peut exécuter un run complet pour un pays placeholder sans confirmation, exposant des montants indicatifs comme réels.

## User Stories & Testing

### User Story 1 — Un run pour un pays placeholder exige la confirmation (P1)
**Acceptance Scenarios**:
1. Given un run en `draft` pour un pays placeholder (ex. BJ), When POST `/payroll-runs/{run}/calculate` sans `acknowledge_placeholder`, Then 422 avec le message localisé `payroll.placeholder_acknowledge_required` et le run reste `draft` (jamais `calculating`).
2. Given le même run, When POST avec `acknowledge_placeholder=true`, Then 200 et le calcul s'exécute.
3. Given l'acceptation, When vérification de l'audit, Then une entrée `AuditLog` `placeholder_warning_acknowledged` est créée (tenant, acteur, pays, contexte `payroll_run_calculate`, run_id).

### User Story 2 — Les pays non-placeholder ne sont pas impactés (P1)
**Acceptance Scenarios**:
1. Given un run pour un pays `production`/`pilot` (ex. DZ), When POST `/calculate` sans paramètre, Then 200 (aucune confirmation requise, aucun audit créé).

## Requirements

### Functional Requirements
- **FR-001**: `PayrollRunController@calculate` DOIT résoudre les règles du pays du run et, si `confidenceLevel() === 'placeholder'`, exiger `acknowledge_placeholder=true` (422 sinon) AVANT tout changement de statut.
- **FR-002**: L'acceptation DOIT être tracée dans `AuditLog` (`placeholder_warning_acknowledged`, mêmes champs que les simulations #1872 + `context=payroll_run_calculate` + `run_id`).
- **FR-003**: OpenAPI : requestBody `acknowledge_placeholder` documenté sur POST `/payroll-runs/{payrollRun}/calculate` + réponse 422.
- **FR-004**: Aucun changement de comportement pour les pays non-placeholder.

## Success Criteria
- **SC-001**: Nouveaux tests Feature (`PayrollRunControllerTest`) : 422 sans confirmation (run reste draft), 200 avec confirmation + audit, 200 non-placeholder sans paramètre.
- **SC-002**: Les tests existants du contrôleur restent verts (mock `getRules` ajouté au fake calculator).

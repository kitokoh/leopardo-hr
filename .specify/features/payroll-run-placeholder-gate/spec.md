# Feature Specification: PAYROLL — Gate acknowledge_placeholder sur le chemin run (PayrollRunController@calculate)

**Feature Branch**: `fix/2332-payroll-run-placeholder-gate`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2332

## Problème

La garde 422 `acknowledge_placeholder=true` pour les pays placeholder (CF/TD/GQ/TG/BJ/NE) existe sur les contrôleurs de simulation (`CotisationSimulationController`, `PayrollSimulationController` — #1872) mais PAS sur le chemin de calcul d'un run réel : `PayrollRunController@calculate` → `PayrollCalculator::calculateRun` peut exécuter un run complet pour un pays placeholder sans confirmation, exposant des montants indicatifs comme s'ils étaient réels.

## User Stories & Testing

### User Story 1 — La garde existe sur le run (P2)
**Acceptance Scenarios**:
1. Given un run `draft` pour un pays placeholder (ex. BJ), When `POST /payroll-runs/{id}/calculate` sans `acknowledge_placeholder`, Then 422 avec erreur `acknowledge_placeholder` et AUCUNE entrée d'audit.
2. Given le même run, When `POST .../calculate` avec `acknowledge_placeholder=true`, Then calcul accepté (200) + entrée `AuditLog` `placeholder_warning_acknowledged` (tenant, acteur, pays).

### User Story 2 — Pas de régression pays pilot/production (P2)
**Acceptance Scenarios**:
1. Given un run pour un pays non-placeholder, When `POST .../calculate` sans paramètre, Then 200 sans exigence ni trace d'audit.

## Plan technique
1. `PayrollRunController::calculate()` : résoudre les règles via `$this->calculator->getRules($payrollRun->country_code)` ; si `confidenceLevel() === 'placeholder'` → 422 si non-acknowledgée, sinon `AuditLog::create` (même pattern #1872 que les simulations).
2. OpenAPI (`api/openapi.yaml` + miroir dev-hub) : `requestBody` `acknowledge_placeholder` documenté sur POST calculate.
3. Tests `PayrollRunControllerTest` : 2 nouveaux (garde 422 + audit ; non-régression pays non-placeholder) + mock `getRules`.
4. CHANGELOG + PR `fix/2332-...` `Closes #2332`.

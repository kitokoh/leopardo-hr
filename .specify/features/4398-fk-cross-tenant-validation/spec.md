# Feature Specification: FK cross-tenant — validation exists scopée compagnie (Closes #4398)

**Feature Branch**: `fix/4398-fk-cross-tenant-validation`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4398 (P2, api, security — famille #3065/#3428)

## Contexte

`Store/UpdateDepartmentRequest` validaient `manager_id` comme `nullable|integer|min:1`
et `Store/UpdatePositionRequest` `department_id` pareil — aucune règle `exists`
scopée compagnie. Un manager pouvait référencer un employé/département d'UNE
AUTRE compagnie → relations cassées, données sales (exports/paie incohérents).

## User Stories & Testing

### User Story 1 — Les FK HR restent dans la compagnie (P1)

**Acceptance Scenarios**:
1. Given un manager de compagnie A, When POST /departments avec
   `manager_id` = employé de compagnie B, Then 422 `manager_id`.
2. Given un manager de compagnie A, When POST /positions avec
   `department_id` = département de compagnie B, Then 422 `department_id`.
3. Given les mêmes FK dans la compagnie A, Then 201.

## Requirements

### Functional Requirements

- **FR-001**: `manager_id` → `Rule::exists('employees','id')->where(company_id = user)` ×2 Requests.
- **FR-002**: `department_id` → `Rule::exists('departments','id')->where(company_id = user)` ×2 Requests.
- **FR-003**: helper `companyId()` (pattern #3065/#3428) dans chaque Request.
- **FR-004**: test `DepartmentPositionFkIsolationTest` (4 scénarios).

## Success Criteria

- **SC-001**: 4 tests verts (2 rejets cross-tenant 422 + 2 acceptations 201).
- **SC-002**: PHPStan strict vert, Pint propre.

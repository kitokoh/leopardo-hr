# Feature Specification: OrgChart — subordinates/manager-chain autorisés (Closes #4497)

**Feature Branch**: `fix/4497-orgchart-authz`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4497 (P2, api, security)

## Contexte

`OrgChartController::subordinates()`/`managerChain()` (routes authentifiées
simples) exposent les subordonnés et la chaîne de management de N'IMPORTE
QUEL `employee_id` — emails inclus — à tout employé connecté, sans contrôle
`isManager()`/scope équipe. `EmployeePolicy::view` refuse explicitement ces
données aux non-managers ailleurs.

## User Stories & Testing

### User Story 1 — Seuls soi-même et les managers consultent l'organigramme ciblé (P1)

En tant qu'employé non-manager, je veux ne pouvoir consulter mes propres
subordonnés/chaîne, et en tant que manager, ceux de mon périmètre.

**Acceptance Scenarios**:
1. Given un employé non-manager, When il interroge `subordinates`/`manager-chain`
   d'un autre employee_id, Then 403.
2. Given le même employé, When il interroge ses propres données, Then 200
   (comportement existant conservé).
3. Given un manager (company-wide ou team-scoped), When il interroge un
   employé de son périmètre visible, Then 200 ; hors périmètre → 403.

## Requirements

- **FR-001**: `subordinates`/`managerChain` : 403 si `$actor->id !== $employeeId`
  et l'acteur n'est pas manager.
- **FR-002**: pour les managers team-scoped (dept/superviseur), la cible est
  résolue via `visibleToManager()` (périmètre équipe) ; hors périmètre → 403.
- **FR-003**: `index()` inchangé (organigramme company-wide en lecture seule).
- **FR-004**: tests feature d'isolation de rôle ; PHPStan strict vert ;
  CHANGELOG.md mis à jour.

## Success Criteria

- **SC-001**: non-manager → 403 pour un autre employee_id (subordinates ET manager-chain).
- **SC-002**: 200 pour soi-même ; 200 pour un manager sur son périmètre.
- **SC-003**: suite `OrgChartControllerTest` verte.

## Hors périmètre

- L'email reste exposé dans `subordinates` pour les managers légitimes
  (nécessaire au fonctionnement manager) — le scope d'équipe limite le risque.

# Feature Specification: Workflow de validation RBAC de la paie (issue #5246)

**Feature Branch**: `mod/payroll/5246-validation-rbac`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5246 — Paie DZ : simulation + workflow de validation
(RH → comptable → principal), audit trail, verrouillage, tests RBAC complets.

## Constat

- Le middleware de route `api.manager:principal,comptable` gate DÉJÀ toute la
  chaîne `/payroll-runs/*` (calcul, validate, lock, unlock, bulk-pay —
  `payroll_engine.php`), conformément à la matrice RBAC_ROUTE_MATRIX.md
  (F-11/#1541) — la séparation RH vs comptable/principal est donc appliquée
  au niveau route.
- Les guards controller (`isManager()`) étaient PLUS PERMISSIFS que le contrat
  route (tout manager, y compris rh) → brèche de défense en profondeur si le
  middleware était retiré/contourné.
- Audit trail complet déjà en place (`payroll_run_validated/locked/unlocked`,
  `PayrollClosingService::writeAudit`).
- Simulation dry-run : `POST /payroll/simulate` existe, gated principal/comptable.

## Décision

1. `PayrollRunController::validateRun/lock/unlock` : remplacer `isManager()`
   par `hasManagerRole('principal', 'comptable')` → `abort(403, 'INSUFFICIENT_ROLE')`
   (défense en profondeur, alignée sur le middleware).
2. `EmployeeFactory::managerComptable()` : nouvel état factory réutilisable.
3. Nouveau `PayrollValidationRbacTest` (6 tests / 24 assertions) : rh refusé
   sur calcul/validate/lock/unlock (INSUFFICIENT_ROLE), comptable valide +
   verrouille, principal valide + verrouille + déverrouille, employé 403,
   cross-tenant 404, audit trail écrit.
4. RBAC_ROUTE_MATRIX.md : note défense en profondeur + référence #5246.

## User Scenarios & Testing

1. **Given** un manager `rh`, **When** il appelle calculate/validate/lock/unlock,
   **Then** 403 `INSUFFICIENT_ROLE` (séparation des tâches).
2. **Given** un comptable, **When** il valide puis verrouille un run calculé
   avec bulletins, **Then** 200 + statuts validated → locked.
3. **Given** un principal, **When** il valide/verrouille/déverrouille, **Then** 200.
4. **Given** un employé, **When** il valide, **Then** 403 `MANAGER_REQUIRED`.
5. **Given** un comptable d'une autre société, **When** il valide, **Then** 404.
6. **Given** un run validé + verrouillé par comptable, **Then** audit
   `payroll_run_validated` + `payroll_run_locked` écrits.

## Validation

- 6 nouveaux tests verts ; 26 tests clôture/state-machine sans régression.
- Pint PASS, PHPStan strict 0 erreur.

# Feature Specification: Snapshot leave_balances synchronisé (used/pending)

**Feature Branch**: `fix/2329-leave-balances-snapshot-sync`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2329

## Contexte

Après approbation d'une absence, `GET /me/leave-balances` et `/leave-policies`
affichent toujours `used=0` et un `remaining` inchangé. Le comptage réel vit
dans la chaîne `leave_balance_logs` (source de vérité), mais le snapshot
`leave_balances` servi par `LeavePolicyController@balances/myBalances` n'est
jamais synchronisé : seuls `AccrueLeaveBalances` et `LeaveCarryForward`
écrivent `balance` (increment uniquement). `used` et `pending` ne sont écrits
nulle part.

## User Stories & Testing

### User Story 1 — pending visible dès la demande (P1)
**Acceptance Scenarios**:
1. Given un employé avec un solde, When il pose une absence déductible (pending),
   Then `leave_balances.pending` augmente du nombre de jours.
2. Given le snapshot n'existe pas encore, When la demande est créée,
   Then une ligne snapshot (balance 0) est créée avec `pending` = jours.

### User Story 2 — approbation → used, rejet → restauration (P1)
**Acceptance Scenarios**:
1. Given une absence pending déductible, When approbation,
   Then `pending` diminue des jours et `used` augmente des jours.
2. Given une absence approuvée, When rejet,
   Then `used` diminue des jours (restauration).
3. Given une absence pending, When rejet,
   Then `pending` diminue des jours.
4. Given une absence pending, When annulation,
   Then `pending` diminue des jours.

### User Story 3 — isolation et types non déductibles (P1)
**Acceptance Scenarios**:
1. Given un type non déductible, When demande/approbation/rejet,
   Then aucune ligne snapshot n'est touchée.
2. Given deux tenants, When flux d'absence du tenant A,
   Then les lignes du tenant B restent intactes.

## Contraintes

- `leave_balance_logs` reste la source de vérité du solde disponible
  (contrôle `InsufficientLeaveBalanceException` inchangé).
- Le snapshot est synchronisé dans `AbsenceService`
  (`api/app/Modules/Planning/Infrastructure/Services/AbsenceService.php`).
- Ligne snapshot keyée `(company_id, employee_id, absence_type_id, year)`,
  année dérivée de `start_date`.

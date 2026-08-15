# Plan technique — Snapshot leave_balances synchronisé

## Analyse

`AbsenceService` est l'unique point de mutation des statuts d'absence
(create/approve/reject/cancel), via `AbsenceController` et les actions
`ApproveLeave`/`RejectLeave`.

## Architecture

- **Modèle** : `App\Modules\Planning\Domain\Models\LeaveBalance`
  (table `leave_balances`, colonnes `balance`, `used`, `pending`, `year`,
  unique `(employee_id, absence_type_id, year)`).
- **Service** : `AbsenceService` — ajout d'un helper privé
  `syncLeaveBalanceSnapshot(Absence, string $action)` appelé :
  - `create()` → `pending_add` (pending += jours)
  - `approve()` → `approve` (pending -= jours, used += jours)
  - `reject()` → `reject_pending` ou `reject_approved` selon le statut antérieur
  - `cancel()` → `cancel` (pending -= jours)
- Les types `deducts_leave = false` ne touchent jamais le snapshot.

## Décisions

1. `firstOrCreate` du snapshot : garantit que la ligne existe même sur un
   tenant où l'accrual n'a jamais initialisé la ligne (comportement déjà en
   place dans `LeavePolicyController@adjustBalance`).
2. Les deltas sont calculés avec `max(0, …)` pour éviter des valeurs
   négatives en cas de dérive historique.
3. Aucun changement au flux `leave_balance_logs` (source de vérité) ni au
   contrôle de solde `InsufficientLeaveBalanceException`.

## Tests

- Nouveau fichier `api/tests/Feature/Absences/LeaveBalancesSnapshotTest.php` :
  pending sur création, used/pending à l'approbation, restauration au rejet
  (pending et approved), annulation, type non déductible, isolation tenant.

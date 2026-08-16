# Feature Specification: PayrollCycle employeeBalance — forme de réponse canonique (Closes #4500)

**Issue**: #4500 (P3, api, bug) — `employeeBalance` répondait `{data: {...}} + clés plates au racine`
via `['data' => $payload] + $payload`, contrairement à `myBalance` et tous les endpoints payroll.

## Fix
- `PayrollCycleController::employeeBalance` → `['data' => $payload]` uniquement.

## Tests
- `test_manager_employee_balance_uses_standard_data_envelope` : `array_keys === ['data']`.

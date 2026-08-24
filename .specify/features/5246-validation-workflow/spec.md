# Feature Specification: Payroll validation workflow — atomic transition

**Feature Branch**: `mod/payroll/5246-validation-workflow`

**Issue**: #5246

**Status**: Implemented — focused slice

## Scope

When an authorized RH actor validates a calculated payroll run, the run status, all related pay-slip statuses, and the validation audit event must be committed atomically.

The validation transition remains `calculated → validated`. Payroll formulas, role definitions, locking, and country rules are outside this slice.

## Acceptance criteria

1. A run can be validated only from `calculated` and only by an actor belonging to the same company with manager/RH authorization.
2. The run and every related pay slip are changed in the same database transaction.
3. The validation audit event is written in that same transaction.
4. If the bulletin update or audit write fails, the run remains `calculated` and no partial validation is visible.
5. Existing API response and error-code contracts remain unchanged.

## Verification

The existing Payroll closing E2E tests verify the API transition, validated bulletin statuses, and audit event. PHP syntax, Pint, PHPStan, and the targeted Payroll test suite must pass in PostgreSQL CI.

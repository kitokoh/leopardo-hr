# EXECUTION BLOCKERS AND NEXT ACTIONS
# Version 4.1.4 | 2026-04-04

This file tracks what still cannot be solved by documentation alone.

## Current hard blockers

1. Backend app skeleton not initialized (`api/` has migrations/docs only, no Laravel app code).
2. Mobile app skeleton not initialized (`mobile/` has mocks/docs only).
3. GitHub branch protection must be configured in repo settings (external to local files).
4. Physical `bon-fixed` directory still locked by OS process (already removed from Git tracking).

## Immediate next actions (ordered)

1. Run `CC-01` to initialize Laravel app and test baseline.
2. Run `JU-01` to initialize Flutter app and baseline tests.
3. Configure branch protection using `.github/BRANCH_PROTECTION_REQUIRED.md`.
4. Run one staging restore drill and log in `RUNBOOK_DRILLS_LOG.md`.
5. Run one staging rollback drill and log in `RUNBOOK_DRILLS_LOG.md`.

## Ready check before starting feature tickets

- `INDEX_CANONIQUE.md` read
- `BACKLOG_PHASE1_UNIQUE.md` ticket selected
- `tools/check-governance.ps1` passes

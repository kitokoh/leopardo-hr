# Feature Specification: Jobs sans failed() — échecs silencieux (Closes #4399)

**Feature Branch**: `fix/4399-jobs-failed-handler`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4399 (P3, api, observability — résiduel #3600)

## Contexte

`SendTrialDripEmailJob` (tries=3, backoff=300, sans timeout ni failed()) et
`PublishScheduledPostJob` (tries=3, backoff=30, timeout=60, sans failed())
échouaient en silence : le job restait en queue failed sans log de contexte
ni nettoyage — un post planifié pouvait rester `scheduled` pour toujours.

## User Stories & Testing

### User Story 1 — Un échec définitif est visible et terminal (P1)

**Acceptance Scenarios**:
1. Given un post `scheduled`, When `failed()` (Ayrshare down), Then statut `failed`.
2. Given un drip email en échec, When `failed()`, Then log context (company, day).

## Requirements

### Functional Requirements

- **FR-001**: `failed(\Throwable)` sur les 2 jobs (log contexte).
- **FR-002**: `PublishScheduledPostJob::failed` marque le post `failed` (statut terminal, `withoutGlobalScopes`).
- **FR-003**: `$timeout = 120` sur `SendTrialDripEmailJob`.
- **FR-004**: test `test_failed_handler_marks_post_as_failed`.

## Success Criteria

- **SC-001**: test vert ; PHPStan strict vert ; Pint propre.

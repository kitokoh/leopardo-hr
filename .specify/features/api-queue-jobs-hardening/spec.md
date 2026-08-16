# Feature Specification: Hardening jobs queue — tries/backoff/failed (issue #3600)

**Feature Branch**: `fix/3600-queue-jobs-tries-backoff`
**Created**: 2026-08-15
**Status**: Implemented — validation locale (Pint, PHPStan ciblé, PHPUnit 4/4)

## Problème

- `DispatchCommunicationJob`, `ProvisionDemoTenantJob`, `WarmPaySlipPdfPathsForPayrollRunJob` : aucun `$tries`/`$backoff`/`$timeout`/`failed()`.
- `ProvisionDemoTenantJob` avalait les `\Throwable` → erreur transitoire = échec définitif sans retry alors que le prospect polle `GET /trial/status` (parcours P0).
- Worker Render `queue:work redis --tries=3 --timeout=300` sans backoff → retries en rafale.

## User Stories & Testing

### US1 — Retry borné avec backoff (P1)
**Acceptance Scenarios**:
1. Given une erreur transitoire dans `ProvisionDemoTenantJob`, When le job échoue, Then l'exception est rethrowée (retry worker), le statut `trial_provisionings` reste `pending` pendant les retries.
2. Given le dernier essai échoue, When `failed()`, Then le statut passe `failed` avec l'erreur tronquée.
3. Given `DispatchCommunicationJob`/`WarmPaySlipPdfPathsForPayrollRunJob`, When instanciés, Then `tries=3`, `timeout` borné, `backoff()` défini.

### US2 — Provisioning idempotent (P1)
**Acceptance Scenarios**:
1. Given un retry ou une double soumission pour le même email, When `ProvisionGuidedTrial::execute()` ×2, Then un seul tenant sandbox (même company_id/manager_id).

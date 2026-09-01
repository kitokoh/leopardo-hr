# Feature Specification: API — contrat, intégrité tenant, performance, résilience jobs (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Audit API Laravel du 2026-08-15 (routes vs OpenAPI, validation, N+1, jobs, mass-assignment).

## User Scenarios & Testing

### US1 — Allowlist OpenAPI saine (Priority: P3) — Issue #3596
Purger les 29 entrées devenues documentées ; la garde détecte la staleness.

### US2 — Mass-assignment verrouillé (Priority: P3) — Issue #3597
Champs sensibles (role/status/company_id/*_secret/locked_until/salary_base) hors `$fillable`.

### US3 — Listes training sans N+1 (Priority: P2) — Issue #3598
`session.course:id,title` eager-loadé ; tests assertQueryCount.

### US4 — Intégrité FK tenant (Priority: P3) — Issue #3599
`exists:` scopés company_id (HrController, ContractController) ; exists sur manager_id/project_id ; StoreContractRequest supprimée ou réalignée.

### US5 — Jobs résilients (Priority: P2) — Issue #3600
`$tries`/`$backoff` sur DispatchCommunicationJob/ProvisionDemoTenantJob/WarmPaySlipPdfPathsForPayrollRunJob ; rethrow dans ProvisionDemoTenantJob ; `failed()` sur les jobs argent.

**Acceptance Scenarios**:
1. **Given** une panne DB transitoire pendant le provisioning trial, **When** le job échoue, **Then** il est retenté (le client qui polle /trial/status obtient son tenant).
2. **Given** 50 enrollments, **When** GET /v1/training/enrollments, **Then** requêtes bornées (< 10).

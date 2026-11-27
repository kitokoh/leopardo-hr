# Inventaire CI

- Workflows : **89**
- Mono-job : **62**
- Déclenchés sur `pull_request` : **50**
- Mono-job ET sur `pull_request` (candidats au regroupement) : **35**

## Mono-job sur pull_request

| Workflow | Déclencheurs |
|---|---|
| `actionlint.yml` | merge_group, pull_request, push |
| `actions-convention-guard.yml` | pull_request, workflow_dispatch |
| `application-layer-placement-guard.yml` | pull_request, workflow_dispatch |
| `backend-jobs-ci.yml` | pull_request, push, workflow_dispatch |
| `branch-protection-guard.yml` | pull_request, schedule, workflow_dispatch |
| `ci-secrets-doc-guard.yml` | pull_request, push, workflow_dispatch |
| `cleanup-orphan-runs.yml` | pull_request, schedule, workflow_dispatch |
| `country-catalog-check.yml` | pull_request, push |
| `desktop-ci.yml` | pull_request, push, workflow_dispatch |
| `e2e-isolated.yml` | pull_request, workflow_dispatch |
| `edge-ci.yml` | pull_request, push |
| `fix-feat-ratio-guard.yml` | pull_request, workflow_dispatch |
| `funnel-e2e-gate.yml` | pull_request, workflow_dispatch |
| `kiosk-ci.yml` | pull_request, push, workflow_dispatch |
| `mcp-server-ci.yml` | pull_request, push |
| `merge-health-ci.yml` | pull_request, push, workflow_dispatch |
| `merge-quota-guard.yml` | pull_request, schedule, workflow_dispatch |
| `mobile-core-duplication-guard.yml` | pull_request, push |
| `mobile-distribution-status-guard.yml` | pull_request, schedule, workflow_dispatch |
| `mobile-layer-guard.yml` | pull_request, push, workflow_dispatch |
| `mobile-smoke.yml` | pull_request, workflow_dispatch |
| `onboarding-smoke.yml` | pull_request, push, workflow_dispatch |
| `platform-mfa-coverage-guard.yml` | pull_request, push, workflow_dispatch |
| `pr-external-checks-report.yml` | pull_request, workflow_run |
| `pr-issue-guard.yml` | pull_request |
| `public-promises-guard.yml` | pull_request, workflow_dispatch |
| `public-surfaces-deploy-parity-guard.yml` | pull_request, push, workflow_dispatch |
| `repo-hygiene-guard.yml` | pull_request, push |
| `resource-rbac-guards.yml` | pull_request, push |
| `route-owner-and-orphan-interfaces-guard.yml` | pull_request, push, workflow_dispatch |
| `secret-scan.yml` | pull_request, push, workflow_dispatch |
| `trade-b2c-ci.yml` | pull_request, push, workflow_dispatch |
| `vitrine-lock-guard.yml` | pull_request |
| `web-layer-guard.yml` | pull_request, push, workflow_dispatch |
| `web-offline-ci.yml` | pull_request, push, workflow_dispatch |

## Tous les workflows

| Workflow | Jobs | Déclencheurs | Filtre paths |
|---|---:|---|---|
| `accounting-ci.yml` | 2 | pull_request, push, workflow_dispatch | oui |
| `actionlint.yml` | 1 | merge_group, pull_request, push | non |
| `actions-convention-guard.yml` | 1 | pull_request, workflow_dispatch | oui |
| `admin-pages-deploy-guard.yml` | 1 | schedule, workflow_dispatch | non |
| `application-layer-placement-guard.yml` | 1 | pull_request, workflow_dispatch | oui |
| `architecture-check.yml` | 6 | merge_group, pull_request, push | non |
| `backend-feature-shards.yml` | 1 | workflow_dispatch | non |
| `backend-jobs-ci.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `bc-batch-branch-protocol.yml` | 1 | schedule, workflow_dispatch | non |
| `branch-hygiene.yml` | 1 | schedule, workflow_dispatch | non |
| `branch-protection-guard.yml` | 1 | pull_request, schedule, workflow_dispatch | oui |
| `canonical-domain-guard.yml` | 1 | schedule, workflow_dispatch | non |
| `ci-observability.yml` | 1 | schedule, workflow_dispatch | non |
| `ci-saturation-report.yml` | 1 | schedule, workflow_dispatch | non |
| `ci-secrets-doc-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `cleanup-orphan-runs.yml` | 1 | pull_request, schedule, workflow_dispatch | non |
| `codeql.yml` | 2 | pull_request, push, schedule | oui |
| `country-catalog-check.yml` | 1 | pull_request, push | oui |
| `coverage-gate.yml` | 2 | merge_group, pull_request, push, workflow_dispatch | oui |
| `crm-branch-protocol.yml` | 1 | schedule, workflow_dispatch | non |
| `database-backup.yml` | 3 | schedule, workflow_dispatch | non |
| `db-standby-failover.yml` | 3 | push, schedule, workflow_dispatch | non |
| `deploy-admin-dashboard.yml` | 1 | push, workflow_dispatch | oui |
| `deploy-drift-guard.yml` | 3 | schedule, workflow_dispatch | non |
| `deploy-main-catchup.yml` | 2 | schedule, workflow_dispatch | non |
| `deploy-main.yml` | 3 | push, workflow_dispatch | non |
| `deploy-prod.yml` | 5 | push, workflow_dispatch | non |
| `desktop-ci.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `desktop-distribute.yml` | 5 | workflow_dispatch | non |
| `dev-keepalive.yml` | 3 | push, schedule, workflow_dispatch | non |
| `dev-stack-switch.yml` | 1 | workflow_call, workflow_dispatch | non |
| `e2e-isolated.yml` | 1 | pull_request, workflow_dispatch | oui |
| `e2e-staging.yml` | 4 | workflow_dispatch | non |
| `edge-ci.yml` | 1 | pull_request, push | oui |
| `fix-composer-lock.yml` | 1 | workflow_dispatch | non |
| `fix-feat-ratio-guard.yml` | 1 | pull_request, workflow_dispatch | non |
| `fix-feat-ratio-report.yml` | 1 | schedule, workflow_dispatch | non |
| `funnel-e2e-gate.yml` | 1 | pull_request, workflow_dispatch | oui |
| `guards-fast.yml` | 17 | pull_request, push, workflow_dispatch | oui |
| `i18n-enterprise.yml` | 2 | pull_request, push | oui |
| `image-scan.yml` | 2 | pull_request, schedule, workflow_dispatch | oui |
| `issue-governance-guard.yml` | 1 | schedule, workflow_dispatch | non |
| `k6-load-smoke.yml` | 3 | workflow_dispatch | non |
| `kiosk-ci.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `launch-api-profile-smoke.yml` | 1 | workflow_dispatch | non |
| `launch-observability-smoke.yml` | 1 | schedule, workflow_dispatch | non |
| `lighthouse.yml` | 2 | pull_request, push, schedule, workflow_dispatch | oui |
| `mcp-server-ci.yml` | 1 | pull_request, push | oui |
| `merge-health-ci.yml` | 1 | pull_request, push, workflow_dispatch | non |
| `merge-quota-guard.yml` | 1 | pull_request, schedule, workflow_dispatch | non |
| `mobile-apps-ci.yml` | 4 | pull_request, push, workflow_dispatch | oui |
| `mobile-core-duplication-guard.yml` | 1 | pull_request, push | oui |
| `mobile-distribute-main.yml` | 1 | push, workflow_dispatch | oui |
| `mobile-distribute.yml` | 1 | push, workflow_dispatch | non |
| `mobile-distribution-status-guard.yml` | 1 | pull_request, schedule, workflow_dispatch | non |
| `mobile-layer-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `mobile-smoke.yml` | 1 | pull_request, workflow_dispatch | oui |
| `onboarding-smoke.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `openapi-ci.yml` | 2 | pull_request, push | oui |
| `owasp-zap.yml` | 2 | workflow_dispatch, workflow_run | non |
| `pages-deploy.yml` | 1 | push, workflow_dispatch | oui |
| `payroll-ci.yml` | 5 | pull_request, push, workflow_dispatch | oui |
| `phpstan-baseline.yml` | 1 | workflow_dispatch | non |
| `platform-mfa-coverage-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `post-merge-conventions-audit.yml` | 1 | push | non |
| `pr-external-checks-report.yml` | 1 | pull_request, workflow_run | non |
| `pr-issue-guard.yml` | 1 | pull_request | non |
| `prod-keepalive.yml` | 1 | schedule, workflow_dispatch | non |
| `public-promises-guard.yml` | 1 | pull_request, workflow_dispatch | oui |
| `public-surfaces-deploy-parity-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `public-surfaces-deploy.yml` | 2 | pull_request, push, workflow_dispatch | oui |
| `queue-supervision.yml` | 1 | schedule, workflow_dispatch | non |
| `release-drift.yml` | 1 | schedule, workflow_dispatch | non |
| `release.yml` | 1 | push, workflow_dispatch | non |
| `repo-hygiene-guard.yml` | 1 | pull_request, push | non |
| `resource-rbac-guards.yml` | 1 | pull_request, push | oui |
| `route-owner-and-orphan-interfaces-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `secret-history-scan.yml` | 1 | schedule, workflow_dispatch | non |
| `secret-scan.yml` | 1 | pull_request, push, workflow_dispatch | non |
| `stack-consumption-report.yml` | 1 | schedule, workflow_dispatch | non |
| `stack-switch-dispatch.yml` | 2 | repository_dispatch | non |
| `stack-switch.yml` | 1 | repository_dispatch, workflow_call, workflow_dispatch | non |
| `tests.yml` | 7 | pull_request, push, workflow_dispatch | oui |
| `trade-b2c-ci.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `vitrine-lock-guard.yml` | 1 | pull_request | oui |
| `web-ci.yml` | 3 | pull_request, push, workflow_dispatch | oui |
| `web-layer-guard.yml` | 1 | pull_request, push, workflow_dispatch | oui |
| `web-marketing-ci.yml` | 3 | pull_request, push, workflow_dispatch | oui |
| `web-offline-ci.yml` | 1 | pull_request, push, workflow_dispatch | oui |

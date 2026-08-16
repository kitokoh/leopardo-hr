# Tasks: Jobs queue hardening

- [x] [T1] [P] [US1] `ProvisionDemoTenantJob` : `$tries=3`, `$timeout=120`, `backoff()=[15,60]`, rethrow dans le catch, `failed()` posant le statut final.
- [x] [T2] [P] [US1] `DispatchCommunicationJob` : `$tries=3`, `$timeout=120`, `backoff()=[10,60]`, `failed()` loggé.
- [x] [T3] [P] [US1] `WarmPaySlipPdfPathsForPayrollRunJob` : `$tries=3`, `$timeout=300`, `backoff()=[30,120]`, `failed()` loggé.
- [x] [T4] [P] [US2] `ProvisionGuidedTrial::execute()` : garde d'idempotence par email (metadata provisioned_by=guided_trial) + réutilisation du manager.
- [x] [T5] [P] [US1+US2] Tests : rethrow + statut pending pendant retries + failed() final ; tries/backoff exposés ; idempotence (2 exécutions = 1 tenant).

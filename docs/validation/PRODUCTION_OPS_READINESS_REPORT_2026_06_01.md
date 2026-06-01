# Production ops readiness report - 2026-06-01

## Decision

Le Plan 68.4 est livre au niveau audit operationnel : les procedures de deploy, rollback, worker, scheduler, Redis, notifications, Firebase Distribution, backup et restore sont reliees par documentation et workflows.

## Preuves actuelles

| Domaine | Preuve |
|---|---|
| Deploy API | `.github/workflows/deploy-main.yml`, `DEPLOYMENT_GUIDE.md` |
| Rollback | `RENDER_ROLLBACK_HOOK_URL`, `RUNBOOK_ROLLBACK.md`, section rollback `DEPLOYMENT_GUIDE.md` |
| Workers/queues | `DEPLOYMENT_GUIDE.md`, `backend-jobs-ci.yml`, `queue:health-check` |
| Redis | Variables `QUEUE_CONNECTION=redis`, `REDIS_URL`, `REDIS_CLIENT=predis` documentees |
| Scheduler | `schedule:run` / `schedule:work` documentes |
| Notifications | `MOBILE_NOTIFICATIONS_PRODUCTION_PROOF_2026_06_01.md` |
| Firebase Distribution | `MOBILE_FIREBASE_DISTRIBUTION.md`, `deploy-main.yml`, `mobile-distribute.yml` |
| Backup/restore | `database-backup.yml`, `RUNBOOK_BACKUP_RESTORE.md`, `backup_drill.sh` |

## Secrets/envs critiques a verifier avant lancement

- Render/API : `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `DATABASE_URL`, `REDIS_URL`, `QUEUE_CONNECTION=redis`.
- Deploy : `RENDER_DEPLOY_HOOK_URL`, `RENDER_ROLLBACK_HOOK_URL`.
- Firebase mobile : `FIREBASE_TOKEN`, `FIREBASE_EMPLOYEE_ANDROID_APP_ID`, `FIREBASE_MANAGER_ANDROID_APP_ID`, `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID`.
- Firebase readback : `FIREBASE_SERVICE_ACCOUNT_JSON`, puis `FIREBASE_READBACK_REQUIRED=true` seulement apres rotation et test.
- Notifications backend : `FIREBASE_PROJECT_ID`, `FIREBASE_CREDENTIALS` ou service account utilisable par le worker.
- Backup : `DATABASE_URL`, `RESTORE_DB_URL`, `BACKUP_S3_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`.

## Risques restants

- Confirmer sur Render que le worker queue et le scheduler sont bien des processus separes de l'API HTTP.
- Verifier en production que `queue:health-check` expose les queues `documents,pdf,payroll,notifications,webhooks,default`.
- Rejouer un upload Firebase App Distribution apres rotation definitive du service account.
- Garder un drill restore mensuel avec trace dans `RUNBOOK_DRILLS_LOG.md`.

## Validation executee

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/validate-production-ops-readiness.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict
```

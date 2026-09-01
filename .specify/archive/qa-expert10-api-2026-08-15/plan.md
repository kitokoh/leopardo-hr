# Implementation Plan: API hardening wave

1. Allowlist : script compare chaque entrée à openapi.yaml ; suppression des 29 ; mode `--strict-staleness`.
2. Modèles : réduire `$fillable` (Employee, User, SuperAdmin, Department, UserInvitation, SalaryAdvance, Planning Task) ; test d'architecture anti-régression.
3. TrainingController + PlatformAdminTrainingController : `session.course:id,title` ; tests de comptage de requêtes.
4. Validation : `Rule::exists(...)->where('company_id', ...)` ; exists sur manager_id/project_id ; décision StoreContractRequest (suppression recommandée).
5. Jobs : propriétés `$tries`/`$backoff`/`$timeout` + `failed()` (Log::error + alerte) ; rethrow ProvisionDemoTenantJob.

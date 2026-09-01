# Feature Specification: QA Expert #2 — API (api/) (2026-08-15)

**Feature**: `qa-expert2-api-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + revue statique experte (rg/scripts) + cross-check issues existantes.

## Contexte

Deuxième vague de test expert de la mission propriétaire (tester « dans tous les sens », consigner chaque manquement selon la méthode Spec Kit, puis implémenter). Les findings ci-dessous sont **nouveaux** : vérifiés contre les ~140 issues ouvertes et les branches/PRs existantes (règle anti-doublon #2400).

## Findings non couverts (issues créées)

### #3055 [P2] API — GET /employees/{id}/leave-balances sans garde de rôle : un employé lit les soldes de tout collègue (copie Planning gardée)

> **Constat** : `GET /api/v1/employees/{employeeId}/leave-balances` n'a aucun garde de rôle : tout utilisateur authentifié du tenant lit les soldes de n'importe quel employé. La copie Planning (gardée) scoping self/manager.
> **Preuve** : - `api/routes/modules/absence.php:30-33` (middleware auth+tenant seulement)
- `api/app/Modules/Absence/Interfaces/Api/V1/Controllers/LeavePolicyController.php:17-35` — `balances()` sans `hasManagerRole`, sans scope `actor`/`manager_id`
- Copie gardée : `api/app/Modules/Planning/.../LeavePolicyController.php` (`myBalances`, `hasManagerRole('principal','rh')`)
> **Impact** : Exposition cross-employé des soldes de congés dans le tenant (PII RH) — même société, mais violation du principe du moindre privilège.

### #3056 [P2] API — essai self-service : réponse verify annonce 14 jours alors que le tenant est provisionné 30 j (parcours guidé 30 j)

> **Constat** : Le self-service trial annonce 14 jours (`trial_days_left=14` dans la réponse verify) alors que le tenant est provisionné à 30 jours ; le parcours guidé dit 30 j.
> **Preuve** : - `api/app/.../SelfServiceTrialController.php:221-222` (14)
- `api/app/.../VerifyTrialSignup.php:191` (14)
- `api/app/.../ProvisionGuidedTrial.php:56` (30)
> **Impact** : Message contractuel incohérent entre les deux parcours d'essai.

### #3057 [P2] API — échec d'envoi OTP trial avalé → 200 « Code envoyé » sans code, aucun resend (résiduel #2678)

> **Constat** : En cas d'échec d'envoi de l'OTP (mail), `RequestTrialSignup` avale l'erreur et répond 200 « code envoyé » — l'utilisateur ne reçoit jamais de code et il n'y a pas de resend.
> **Preuve** : - `api/app/.../RequestTrialSignup.php:41-48` (try/catch silencieux)
> **Impact** : Parcours d'essai bloqué sans message (résiduel de #2678 dont le fix n'a pas touché le mail).

### #3058 [P2] API — webhook email-bounce : services.mail_bounce_webhook.secret défini nulle part → 503 permanent (fix fail-closed #2616 incomplet)

> **Constat** : Le controller email-bounce exige `services.mail_bounce_webhook.secret` qui n'est défini ni dans `config/services.php` ni dans `.env.example` → 503 permanent, la feature est morte (fix #2616 fail-closed incomplet).
> **Preuve** : - `api/app/.../EmailBounceWebhookController.php:32-43`
- `api/config/services.php`, `api/.env.example` : clé absente
> **Impact** : Les rebonds email ne sont jamais traités (hygiène d'expéditeur dégradée).

### #3059 [P3] API — per_page/limit non bornés sur 11 endpoints (approvals, billing, ai-gateway, audit-log, cabinet-documents, contracts, vehicles, vehicle-alerts, vehicle-maintenance, vehicle-trips, payroll-cycles)

> **Constat** : `per_page`/`limit` acceptés sans borne supérieure sur 11 endpoints (hors périmètre de #2682) → un client peut demander des pages énormes.
> **Preuve** : - `ApprovalController.php:116,203`, `BillingController.php:134`, `AIGatewayController.php:57`, `AuditLogController.php:61`, `CabinetDocumentController.php:59`, `ContractController.php:39`, `VehicleController.php:36,186,199,212`, `VehicleAlertController.php:33`, `VehicleMaintenanceController.php:30`, `VehicleTripController.php:36`, `PayrollCycleController.php:206`
> **Impact** : Perf/abus (classe #2682 étendue).

### #3060 [P3] API — clé de signature QR onboarding en fallback codée en dur (fail-open si APP_KEY vide → QR forgeables)

> **Constat** : `OnboardingQrService` a un fallback de clé HMAC codé en dur : si `APP_KEY` est vide, les QR d'onboarding deviennent forgeables.
> **Preuve** : - `api/app/.../OnboardingQrService.php:144-148`
> **Impact** : Sécurité onboarding si mauvaise config (devrait fail-closed).

### #3061 [P3] API — drift OpenAPI résiduel : groupes PA2/platform à 0% (announcements, conversations, impersonations, support-tickets, observability, crm/pipeline, edge/health, onboarding/steps, cabinet/stats, webhooks test)

> **Constat** : Des groupes entiers de routes (pour la plupart récents, PA2) restent absents de `api/openapi.yaml`.
> **Preuve** : - `api/openapi.yaml` vs `api/routes/**` (comparaison scriptée) : announcements, conversations, impersonations, support-tickets, observability, crm/pipeline, edge/health, onboarding/steps, cabinet/stats, webhooks test
> **Impact** : Contrat public incomplet (extension #2662/#2675/#2638).

### #3062 [P3] API — méthode morte TrainingController::indexSessionsAll jamais routée (la route utilise indexAllSessions)

> **Constat** : `TrainingController::indexSessionsAll` n'est routée nulle part ; la route utilise `indexAllSessions`.
> **Preuve** : - `api/app/Modules/.../TrainingController.php:142` vs routes `training.php`
> **Impact** : Code mort.

### #3063 [P3] API — LeavePolicyController dupliqué (modules Absence vs Planning) : la copie Absence est celle non gardée (source de la fuite leave-balances)

> **Constat** : `LeavePolicyController` existe en double : modules Absence (non gardé, source de la fuite RBAC) et modules Planning (gardé). Duplication source de drift.
> **Preuve** : - `api/app/Modules/Absence/Interfaces/Api/V1/Controllers/LeavePolicyController.php` vs `api/app/Modules/Planning/.../LeavePolicyController.php`
> **Impact** : Maintenance/risque de régression (connexe au finding RBAC leave-balances).

### #3064 [P3] API — drift docs↔code : RBAC_ROUTE_MATRIX documente /onboarding-setup* sous api.manager, le code l'ouvre à tous les auth (T118) sans mise à jour

> **Constat** : `docs/security/RBAC_ROUTE_MATRIX.md` documente `/onboarding-setup*` sous `api.manager`, mais le code l'expose à tous les utilisateurs authentifiés — la matrice n'a pas été mise à jour.
> **Preuve** : - `docs/security/RBAC_ROUTE_MATRIX.md:70,138` vs `api/routes/modules/billing.php:20-31`
> **Impact** : Garde de sécurité documentaire obsolète (la garde CI ne détecte pas).

### #3065 [P3] API — POST /employees/link-user : employee_id non validé comme appartenant à la société de l'acteur (FK cross-tenant)

> **Constat** : `POST /api/v1/employees/link-user` ne vérifie pas que l'`employee_id` appartient à la société de l'acteur → un manager peut lier un utilisateur à un employé d'une autre société (le scope global `BelongsToCompany` dépend du tenant courant, mais le lien `user_employee_links` peut croiser les tenants).
> **Preuve** : - `api/app/.../UserEmployeeLinkController.php:20,47-57`
> **Impact** : Lien cross-tenant potentiel (intégrité des données + confusions de comptes).

## Règles d'implémentation
- Une PR par issue avec `Closes #N` dans le body (Constitution §VII).
- Pas de données fabriquées : endpoint réel ou état vide honnête.
- i18n : les 4 locales FR/EN/TR/AR dans le même changement ; jamais de clés brutes affichées.
- Vérifier la garde anti-doublon avant push : `git ls-remote --heads origin | grep <issue>`.
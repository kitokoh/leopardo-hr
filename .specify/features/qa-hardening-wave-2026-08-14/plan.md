# Plan: Vague QA Hardening 2026-08-14

**Input**: spec.md (US1-US4) + Constitution + audit 2026-08-14 (session)

## Architecture / Décisions techniques

- **Mobile employee — endpoints manquants** : ajout backend (rétro-compatible), pas de changement mobile :
  - `GET /api/v1/me/training-enrollments` → `SelfServiceController@myTrainings` (alias) + enrichissement additif de `TrainingEnrollmentResource` (`course_title`, `session_date`, `progress` via `session.course` déjà chargée).
  - `GET /api/v1/me/vehicles` → nouvelle méthode `VehicleController@myVehicles` : `where('company_id', ...)->where('assigned_driver_id', $user->id)`, position Traccar best-effort (try/catch, null si pas de tracker).
- **Cockpit admin — chemins** : le backend sert les routes cockpit sous `/admin/*` (auth `super_admin_api`) ; corriger les 4 vues qui appellent `/v1/...` → `/admin/...` (Chat, Exports hr-reports, Fleet alerts, Marketing OAuth). Le client Axios de l'admin normalise `/v1/` → `/api/v1/` mais ne connaît pas `/admin/` — vérifier le mapping dans `src/services/api.js` (les autres vues admin utilisent déjà `/admin/...` et fonctionnent).
- **Cockpit admin — endpoints tenant à créer** :
  - `GET /api/v1/training/sessions` (toutes les sessions, avec cours) et `GET /api/v1/training/enrollments` (liste paginée) sur `TrainingController` — additif, tests + OpenAPI.
  - `POST /api/v1/webhooks/{webhookEndpoint}/test` sur `WebhookController` — dispatch d'un événement de test (`webhook.test`) tracé dans `webhook_deliveries`, 422 si endpoint invalide.
- **Cockpit admin — suppression des mocks** :
  - `UsersView` : charger les invitations réelles (`GET /invitations`), impersonation réelle (`POST /platform/impersonations`), suppression des `generateMockUsers`/`setTimeout`. Si aucun endpoint « delete user » n'existe, l'action est retirée plutôt que simulée.
  - `AnalyticsView` : KPI/activité/alertes depuis `/admin/dashboard/stats|activities|alerts` ; les sections sans backend (cohorte, funnel, segmentation) passent en état vide documenté ou sont retirées.
  - `SystemView` : Health Check → `/health/live` + `/health/ready` ; les actions sans backend (maintenance, backups, config) → état « non disponible » explicite, plus de simulation.
- **Hygiène** : `.env.example` + clés `BIOMETRIC_RETENTION_MONTHS`, `MAIL_URL` ; boutons morts branchés/retirés ; `legal_reference` envoyé dans le payload TaxRates ; `glass-bg0/50` → token `glass-bg-white/50` (ou équivalent existant) ; `openRequest(id)` utilise l'id ; `UserDetailView` n'affiche pas un autre utilisateur par défaut.
- **Gouvernance contrats** : tout endpoint ajouté = OpenAPI + `FrontendApiContractTest` + `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` + (mobile) `dev-hub/tools/mobile-workflow-contracts.json`.

## Phases

### Phase 1 — Backend endpoints mobiles (US1)
- `me/training-enrollments` (alias + resource enrichie) + tests.
- `me/vehicles` + tests (isolation tenant, empty state, position null-safe).

### Phase 2 — Cockpit : chemins corrigés + endpoints créés (US2)
- 4 vues corrigées (Chat, Exports, Fleet, Marketing OAuth).
- `GET /training/sessions`, `GET /training/enrollments`, `POST /webhooks/{id}/test` + vues Training/Webhooks branchées.
- OpenAPI + tests + matrice.

### Phase 3 — Suppression des mocks cockpit (US3)
- UsersView, AnalyticsView, SystemView → données réelles / états vides honnêtes.

### Phase 4 — Hygiène et petits défauts (US4)
- `.env.example`, boutons morts, legal_reference, glass typo, openRequest, UserDetailView.

## Fichiers touchés (référence)

- `api/app/Modules/HR/Interfaces/Api/V1/Controllers/{SelfServiceController,TrainingController}.php`
- `api/app/Modules/Fleet/Interfaces/Api/V1/VehicleController.php`
- `api/app/Modules/Billing/Interfaces/Api/V1/WebhookController.php`
- `api/app/Http/Resources/Api/V1/TrainingEnrollmentResource.php`
- `api/routes/modules/{rh,hr_extended,payroll_engine?}.php`
- `api/openapi.yaml`, `api/tests/Feature/**`, `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`
- `front/admin-dashboard/src/views/{chat,exports,fleet,marketing,training,webhooks,users,analytics,system,companies,growth,settings,crm,users}/**`
- `front/mobile_apps/leopardo_core/lib/models/{training_enrollment,vehicle_position}.dart` (si ajustement shape)
- `.env.example`, `dev-hub/tools/mobile-workflow-contracts.json`, `CHANGELOG.md`, `AGENTS.md`

## Contraintes

- Ne pas toucher aux branches/PR en cours des autres agents (fix/2136, feat/2116, feat/2121, feat/2131, fix/openapi-*).
- Constitution §IV : PHPStan strict level 8 vert, Pint, tests avant/avec implémentation, isolation tenant testée.
- Constitution §VII : PR par issue avec `Closes #N`, CHANGELOG, branche supprimée après merge.
- Multi-agents : s'assigner les issues avant de coder ; vérifier `gh issue list --assignee @me`.
- Rétro-compat : ne pas renommer/casser `/me/trainings`, `/vehicles/{id}/position`, `/admin/...` existants.

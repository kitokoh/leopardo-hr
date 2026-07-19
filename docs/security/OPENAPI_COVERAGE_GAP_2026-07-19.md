# 📋 Écart de couverture OpenAPI — 2026-07-19

> Généré lors de la revue de suivi de `docs/security/AUDIT_API_2026-07-19.md`.
> Méthode : parsing statique de `routes/api.php` + tous les fichiers `routes/modules/*.php` + `routes/ai.php`
> (reconstruction des préfixes `Route::prefix()->group()` imbriqués), comparé aux chemins/opérations déclarés
> dans `api/openapi.yaml`. Pas d'exécution PHP/Laravel (environnement d'audit sans runtime) — donc **pas de
> vérification dynamique** (`php artisan route:list` n'a pas pu être lancé pour confirmer/purger les faux positifs
> du parseur statique).

## Constat

- **532 routes uniques** reconstruites statiquement (méthode + chemin, préfixes imbriqués résolus).
- **345 opérations** déclarées dans `openapi.yaml` (267 chemins).
- **~210 routes** (après normalisation `{param}` et filtrage des artefacts évidents du parseur) n'ont **aucune
  correspondance** dans `openapi.yaml`.

`CONVENTIONS.md` §7 exige : *"Tout nouvel endpoint DOIT être documenté dans openapi.yaml"*. Cet écart suggère que
la documentation n'a pas suivi le rythme d'ajout de nouveaux modules/endpoints (Payroll engine, Marketing,
Growth/Partenaires, SSO, ZKTeco, Cabinet, exports RH, rapports avancés, IA/agent, notamment).

## Pourquoi ce n'est pas corrigé directement dans cette revue

Documenter correctement ~210 endpoints demande de connaître le vrai schéma de requête/réponse de chacun
(règles de validation `FormRequest`, structure `Resource`/`JsonResponse`). Sans runtime PHP pour introspecter
le code (`php artisan route:list`, exécution des `FormRequest::rules()`, inspection des `Resource::toArray()`),
toute tentative de générer ~210 blocs OpenAPI reviendrait à **deviner** les schémas — ce qui produirait une
documentation techniquement présente mais potentiellement fausse, pire que l'absence de documentation.//
Recommandation : traiter ce fichier comme un ticket de suivi, module par module, avec un mainteneur ayant
accès à un environnement Laravel fonctionnel (idéalement génération semi-automatique via un package
type `dedoc/scramble` ou `knuckleswtf/scribe` plutôt que rédaction manuelle).

## Liste des routes sans correspondance OpenAPI, groupées par module

### AI / Agent (`routes/ai.php`)
- `GET /ai/agent/workflows`
- `GET /ai/workflows/weekly-report`
- `POST /ai/actions/{pendingActionId}/confirm`
- `POST /ai/actions/{pendingActionId}/reject`
- `POST /ai/agent/run`
- `POST /ai/voice/command`
- `POST /ai/voice/synthesize`
- `POST /ai/voice/transcribe`
- `POST /ai/workflows/prepare-payroll`

### Auth / Core (`routes/api.php`)
- `GET /auth/google`, `GET /auth/google/callback`
- `GET /health/live`, `GET /health/ready`, `GET /metrics`
- `GET /i18n/catalog`, `GET /i18n/catalog/{locale}`
- `GET /onboarding/checklist`, `GET /onboarding/invitation/{token}`, `POST /onboarding/invitation/{token}/activate`
- `GET /features/admin/statistics`, `GET /features/compatible/{version}`, `GET /features/manifest`, `GET /features/{key}`, `POST /features/admin/synchronize`
- `GET /company-requests`, `POST /company-requests`
- `POST /trial/signup`, `POST /trial/verify`
- `POST /webhooks/chargily`, `POST /webhooks/stripe` *(webhooks fournisseur — probablement volontairement hors doc publique, à confirmer)*
- `GET /platform/crm/pipeline`, `GET /platform/edge/nodes/`, `POST /platform/edge/nodes/{id}/sync`, `DELETE /platform/edge/nodes/{id}`
- `POST /platform/auth/2fa/setup`, `POST /platform/auth/2fa/enable`, `POST /platform/auth/2fa/disable`

### RH / Départements / Postes / Paie basique (`routes/modules/rh.php`)
- `GET/POST /departments`, `GET/PATCH/PUT/DELETE /departments/{department}`
- `PATCH /positions/{position}`
- `GET /employees/{employee}/balance`
- `GET /payrolls`, `POST /payrolls`, `GET/PATCH/PUT/DELETE /payrolls/{payroll}`, `PUT /payrolls/{payroll}/validate`
- `GET/PATCH /schedules/{schedule}`
- `PATCH /sites/{site}`
- `GET /notifications/stream`, `POST /notifications/sse-token`

### HR étendu (`routes/modules/hr_extended.php`)
- `GET /audit-logs/export-csv`, `GET /audit-logs/{auditLog}`
- `GET/PATCH/DELETE /recruitment/applicants/{id}`, `PATCH /recruitment/applicants/{id}/status`
- `PUT/DELETE /recruitment/interviews/{id}`, `PATCH /recruitment/interviews/{id}/feedback`
- `DELETE /recruitment/jobs/{id}`
- `PUT /loans/{employeeLoan}/approve`, `PUT /loans/{employeeLoan}/disburse`
- `GET /predictions/absenteeism`, `GET /predictions/notifications`, `GET /predictions/turnover`
- `GET /reports/absenteeism`, `.../cost-analysis`, `.../demographics`, `.../headcount`, `.../loan-summary`, `.../overtime`, `.../payroll-summary`, `.../recruitment-pipeline`, `.../training-completion`, `.../turnover`
- `GET /webhooks/events`, `GET/PUT/DELETE /webhooks/{webhookEndpoint}`

### Payroll Engine (`routes/modules/payroll_engine.php`)
- `GET/DELETE /salary-components(/{salaryComponent})`, `POST /salary-components`, `PUT /salary-components/{salaryComponent}`
- `GET/DELETE /salary-structures(/{salaryStructure})`, `POST /salary-structures`, `PUT /salary-structures/{salaryStructure}`
- `GET /tax-slabs`, `POST /tax-slabs`, `PUT/DELETE /tax-slabs/{taxSlab}`
- `GET/PUT/DELETE /social-contributions(/{socialContribution})`, `POST /social-contributions`
- `POST /social-declarations/cnas-dz`, `.../cnss-ma`, `.../dsn-fr`
- `GET /bank-exports/{bankExport}`
- `GET /me/pay-slips/{paySlip}`, `GET /me/pay-slips/{paySlip}/pdf`
- `GET /payroll-runs/{payrollRun}/bulk-pay/status`, `.../export`, `.../pay-slips`
- `POST /payroll-runs/{payrollRun}/bank-export`, `.../bulk-pay`, `.../send-slips`
- `GET /payroll/cycles`, `GET /payroll/cycles/current`
- `POST /cotisation-simulation`
- `GET /onboarding/steps` *(sic — probablement une route mal rangée dans ce fichier, à vérifier)*

### Marketing (`routes/modules/marketing.php`)
- `GET /social-account`, `POST /social-account/connect`, `POST /social-account/disconnect`
- `GET/POST /social-posts`, `GET/PATCH/DELETE /social-posts/{socialPost}`, `POST /social-posts/{socialPost}/publish`

### Growth / Partenaires (`routes/modules/growth.php`)
- `GET /growth/partner/companies`, `.../dashboard`, `.../stats`
- `POST /growth/partner/apply`, `.../payout`
- `GET /platform/growth/history`
- `PATCH /platform/growth/partners/{partner}/application`, `.../payouts/{payout}`

### SSO (`routes/modules/sso.php`)
- `GET /sso/oidc/{companyId}/callback`, `GET /sso/providers`, `GET /sso/status`
- `POST /sso/configure`, `POST /sso/saml/{companyId}/callback`
- `DELETE /sso/disable`

### Intégrations (calendrier, ZKTeco, kiosques) (`routes/modules/integrations.php`)
- `DELETE /calendar/disconnect/{provider}`, `GET /calendar/connections`, `GET /calendar/events`, `POST /calendar/connect`, `POST /calendar/sync`
- `GET/POST /zkteco/devices`, `GET/PUT/DELETE /zkteco/devices/{id}`, `GET /zkteco/devices/{id}/sync-logs`, `POST /zkteco/devices/{serialNumber}/push-users`, `POST /zkteco/heartbeat/{serialNumber}`, `POST /zkteco/sync-attendance/{serialNumber}`
- `GET /kiosks/{deviceCode}/announcements`, `POST /kiosks/{deviceCode}/employee-info`, `.../leave-balance`, `.../qr-punch`

### Cabinet documentaire (`routes/modules/cabinet.php`)
- `GET /cabinet/shared/{token}`, `GET /cabinet/shares`, `POST /cabinet/shares`, `DELETE /cabinet/shares/{cabinetShare}`
- `GET /cabinet/stats`
- `PATCH /cabinet/documents/{cabinetDocument}/move`

### Billing (`routes/modules/billing.php`)
- `GET /billing/portal`, `POST /billing/checkout`, `POST /billing/subscription/upgrade`, `.../cancel`, `.../renew`
- `GET /feature-flags/matrix`, `GET /feature-flags/check/{featureKey}`, `PUT /feature-flags/matrix`

### Dashboard / Exports / Tokens API (`routes/modules/dashboard.php`)
- `GET /dashboard/admin`, `.../comptable`, `.../marketing`, `.../rh`
- `GET /company/team-roles`
- `GET/POST /api-tokens`, `DELETE /api-tokens/{tokenId}`
- `GET /notifications/unread`, `PATCH /notifications/{id}/read`, `POST /notifications/mark-all-read`
- `POST /employees/{employee}/assign-role`
- `GET /export/absences`, `.../accounting-od`, `.../attendance`, `.../contracts`, `.../employees`, `.../history`, `.../pay-slips`, `.../payroll-journal`, `.../payroll-ledger`, `.../training`, `.../vehicles`

### Planning (`routes/modules/planning.php`)
- `GET /planning/shift-rebalancing`, `GET /planning/weekly-optimization`

### Expense (`routes/modules/expense.php`)
- `PUT /expense-claims/{expenseClaim}/approve`, `.../reject`, `.../submit`

### Notification (`routes/modules/notification.php`)
- Chemins signalés `v1/notifications/...` par le parseur statique — **probable faux positif de double-préfixe**
  (le fichier applique déjà `/v1` mais le parseur peut l'avoir compté deux fois). À revérifier avec
  `php artisan route:list --path=notifications` avant d'agir.

### User (app RH legacy) (`routes/modules/user.php`)
- `GET /user/company-requests`, `GET /user/company-requests/{id}`, `POST /user/company-requests`
- `GET /user/employee-links`, `POST /employees/link-user`
- `GET /user/me`, `PATCH /user/profile`
- `POST /user/change-password`, `.../google-signin`, `.../login`, `.../logout`, `.../register`

### App RH mobile (`routes/modules/hr_app.php`)
- `GET /dashboard`, `GET /me`, `GET /team-overview`

### Cameras (`routes/modules/cameras.php`)
- `POST /test-rtsp`

## Faux positifs probables à écarter avant de commencer le travail de documentation

- `POST /webhooks/stripe`, `POST /webhooks/chargily` : webhooks fournisseurs entrants, typiquement exclus des
  specs OpenAPI publiques (pas consommés par les clients API). À confirmer avec l'équipe avant de les ajouter.
- Les 4 entrées `v1/notifications/...` et `platform/edge/nodes/` semblent être des artefacts du parseur statique
  (double-comptage de préfixe) plutôt que de vraies routes non préfixées par `/v1` — à vérifier avec
  `php artisan route:list` (non disponible dans cet environnement d'audit).

## Recommandation

1. Lancer `php artisan route:list --json` dans un environnement avec PHP pour obtenir la liste exacte
   (élimine tout risque d'erreur du parseur statique ci-dessus).
2. Comparer au format canonique avec un script (diff normalisé, comme celui utilisé pour produire ce document).
3. Prioriser la documentation par domaine métier à plus fort risque/usage externe : Payroll Engine, Billing,
   SSO, ZKTeco (accès dispositifs physiques), avant les endpoints internes/reporting à faible exposition.
4. Envisager un générateur semi-automatique (`dedoc/scramble`, `knuckleswtf/scribe`) plutôt qu'une rédaction
   manuelle de ~200 blocs, pour rester synchronisé avec le code au fil du temps (cf. `CONVENTIONS.md` §7).

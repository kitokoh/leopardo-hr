# Audit statique READ-ONLY — API Laravel Leopardo HR (api/)

- Date : 2026-08-15
- Cible : `/home/user/.workspace/leopardo-hr/api` (Laravel 12, modules DDD)
- Méthode : lecture du code uniquement, aucun fichier modifié, aucun push.
- Sévérité : **P1** sécurité/confiance · **P2** disponibilité/intégrité/consistance · **P3** fiabilité/qualité/drift.
- Snippets vérifiés ligne à ligne dans le code source.

---

## P1 — Sécurité

### 1. IDOR cross-tenant : `GET /employees/{employeeId}/leave-balances` sans filtre `company_id` ni garde de rôle
- **Fichier:ligne** : `routes/modules/absence.php:31-34` → `app/Modules/Absence/Interfaces/Api/V1/Controllers/LeavePolicyController.php:16-29`
- **Snippet** :
```php
public function balances(Request $request, int $employeeId): JsonResponse
{
    $balances = LeaveBalance::query()
        ->with('absenceType')
        ->where('employee_id', $employeeId)      // ← aucun where('company_id', ...)
        ->where('year', $request->input('year', now()->year))
```
- La route n'est pas sous `api.manager` (contrairement au canonique `/leave-balances`, `routes/modules/hr_extended.php:82`, qui lui vérifie `company_id` + `isManager()`). N'importe quel employé authentifié (même rôle `ordinary` sans société, cf. constat 2) peut énumérer les soldes de congés (données liées à la rémunération) de n'importe quel employé, y compris cross-tenant : le scope global `BelongsToCompany` ne s'applique que si `current_company` est bound (`app/Shared/Traits/BelongsToCompany.php:29-31`) — il est silencieusement sauté pour les employés `ordinary`.
- **Sévérité : P1**

### 2. OAuth Google crée un employé tenantless avec token valide, et le TenantMiddleware laisse passer le rôle `ordinary` sans tenant
- **Fichier:ligne** : `app/Core/Auth/Interfaces/Api/V1/AuthController.php:196-222` ; contournement : `app/Http/Middleware/TenantMiddleware.php:51-52`
- **Snippet** :
```php
$employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();
if (! $employee) {
    $employee = Employee::create([
        'first_name' => ..., 'email' => ..., 'password_hash' => Hash::make(str()->random(24)),
        'role' => 'ordinary', 'status' => 'active',      // ← AUCUN company_id
    ]);
}
...
$token = $employee->createToken('google-auth');          // ← token Sanctum immédiat (201)
```
- Le middleware tenant ne résout pas de société et, pour `role === 'ordinary'`, passe la requête **sans** poser de tenant (`return $next($request)`). N'importe quel compte Google peut donc se créer un employé orphelin (aucune invitation requise) et obtenir un token valide utilisable sur les routes `auth:sanctum` — combiné au constat 1, lecture cross-tenant ; et le lookup se fait via `withoutGlobalScopes()` (email global, cross-tenant).
- **Sévérité : P1**

### 3. ApprovalRequestPolicy enregistrée mais jamais invoquée ; `approve`/`reject` sans garde manager
- **Fichier:ligne** : `routes/modules/hr_extended.php:55-58` (routes hors `api.manager`) → `app/Modules/Attendance/Interfaces/Api/V1/ApprovalController.php:121-134` et `161-175` ; politique morte : `app/Policies/ApprovalRequestPolicy.php:27-36`
- **Snippet** :
```php
public function approve(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
{
    $actor = $request->user();
    if ($approvalRequest->company_id !== $actor->company_id) { abort(404); }
    if ($approvalRequest->status !== 'pending') { return ...422; }
    // ← AUCUN $this->authorize(), AUCUN isManager()
```
- `ApprovalRequestPolicy::approve()` exige `$actor->isManager()` + `status === 'pending'` (déjà géré dans le Policy), mais zéro `$this->authorize()` dans tout le contrôleur (grep : 0 occurrence). La politique n'est jamais appelée. Tout employé du tenant peut approuver/rejeter des demandes d'approbation (congés, avances…), et `pending()` (`:106-118`) expose toutes les demandes du tenant à tout employé.
- **Sévérité : P1**

### 4. SSRF : `ffprobe` exécuté sur URL `rtsp://` contrôlée par l'utilisateur, sans blocklist réseau
- **Fichier:ligne** : `app/Modules/Cameras/Infrastructure/Services/CameraService.php:376-392` ; requête : `app/Modules/Cameras/Interfaces/Api/V1/Requests/TestRtspRequest.php:15-26` ; route : `routes/modules/cameras.php:29`
- **Snippet** :
```php
if (! preg_match('#^rtsp://[^\s\'"]+$#i', $rtspUrl)) { ... }   // seul filtre : protocole
$cmd = sprintf('%s -v error -rtsp_transport tcp -stimeout %d -i %s -show_streams -of json 2>&1',
    escapeshellcmd($binary), $timeout * 1_000_000, escapeshellarg($rtspUrl));
$output = @shell_exec($cmd);                                    // ← shell_exec, erreurs masquées
```
- La regex autorise n'importe quel hôte (`10.0.0.5`, `169.254.169.254`, `localhost`) et n'importe quel port via `host:port` → scan réseau interne / probing de services depuis le serveur, déclenché par un `principal` authentifié. Aucun blocklist IP privée/loopback, aucun allowlist réseau caméras.
- **Sévérité : P1** (SSRF authentifié, protocole restreint au handshake RTSP mais port-scan possible)

### 5. Clé de signature QR en fallback hardcodée (fail-open)
- **Fichier:ligne** : `app/Modules/Onboarding/Infrastructure/Services/OnboardingQrService.php:144-148`
- **Snippet** :
```php
private function signingKey(): string
{
    $key = (string) Config::get('app.key', '');
    return $key !== '' ? $key : 'leopardo-local-onboarding-key';   // ← clé publique connue
}
```
- Si `APP_KEY` est absente/vide en prod, tous les QR tokens (employee profile, company onboarding) sont signés avec une constante publiée dans le code. Forge de QR valides → `createEmployeeFromQr` (`OnboardingQrController.php:225-290`) crée des employés dans la société cible, `scanCompany` crée des demandes d'intégration. Fail-open au lieu de fail-closed.
- **Sévérité : P1**

---

## P2 — Races / consistance / disponibilité

### 6. `ProvisionDemoTenantJob` appelle `issueDemoAccess()` 2× → 2 emails, 1er token invalidé
- **Fichier:ligne** : `app/Jobs/ProvisionDemoTenantJob.php:42` **et** `:63` (méthode `:83-114`)
- **Snippet** :
```php
$this->issueDemoAccess($result['manager']);                 // ligne 42 (fix #2629)
...
if (isset($result['manager']) && $result['manager'] instanceof Employee) {
    $this->issueDemoAccess($result['manager']);             // ligne 63 (fix #2620) — doublon
}
```
- Chaque appel régénère un token aléatoire et **écrase** `extra_data.demo_access_token_hash` ; le premier lien envoyé devient invalide. Deux emails envoyés, un seul lien fonctionne (le 2e). Régression issue de la fusion des correctifs #2858 + #2864.
- **Sévérité : P2**

### 7. `/trial/verify` répond `days: 30` mais `ends_at: +14` — statut incohérent et mensonger
- **Fichier:ligne** : `app/Modules/Billing/Interfaces/Api/V1/SelfServiceTrialController.php:219-224`
- **Snippet** :
```php
'trial' => [
    'days' => 30,                                        // ← 30 jours annoncés
    'ends_at' => now()->addDays(14)->toIso8601String(),  // ← expire dans 14 jours
],
'next_steps' => ['login' => 'Connectez-vous avec votre email et le mot de passe ci-dessus.'],
```
- Le tenant est réellement provisionné avec `subscription_end = +14 jours` (`VerifyTrialSignup.php:235`). La réponse ne contient **aucun** mot de passe alors que le texte renvoie vers « le mot de passe ci-dessus ». `days:30` est un mensonge (hardcodé), `ends_at` dit la vérité : contradiction dans la même réponse.
- **Sévérité : P2**

### 8. `trial_days` du plan ignoré : 14 jours hardcodés sur les deux chemins de provisioning
- **Fichier:ligne** : `app/Modules/Billing/Application/Actions/VerifyTrialSignup.php:235` ; `app/Modules/Billing/Application/Actions/ProvisionGuidedTrial.php:56`
- **Snippet** :
```php
'subscription_end' => now()->addDays(14)->toDateString(),   // ignore $plan->trial_days
```
- `resolveTrialPlanId()`/`resolveTrialPlan()` prennent le **premier plan actif** dont `trial_days` peut être 7/30/…, mais les deux chemins trial forcent 14 jours. Le chemin plateforme, lui, respecte le plan (`app/Modules/Platform/Infrastructure/Services/CompanyProvisioningService.php:49,66` : `$plan->trial_days ?? 14`). Durée d'essai incohérente entre chemins.
- **Sévérité : P2**

### 9. Essai guidé : mot de passe manager jamais communiqué (accès uniquement par magic link best-effort)
- **Fichier:ligne** : `app/Modules/Billing/Application/Actions/ProvisionGuidedTrial.php:64-66`
- **Snippet** :
```php
'password_hash' => Hash::make(Str::random(16)),   // ← mot de passe aléatoire, JAMAIS transmis
```
- Aucun email avec mot de passe ; le seul canal est le magic link (constat 6), envoyé en best-effort (`catch (\Throwable $exception)` dans `issueDemoAccess`, `ProvisionDemoTenantJob.php:98-105`). Si le mail échoue, `trial_provisionings.status` passe quand même à `ready` avec `login_url => '/auth/login'` (`ProvisionDemoTenantJob.php:47-54`) : le prospect ne peut se connecter nulle part. Confirmé par QA interne : « mot de passe manager jamais communiqué, trial/status dit ready sans credentials » (`docs/qa/QA_SESSION_2026-08-15.md` API-4, #2629).
- **Sévérité : P2**

### 10. Bulk-pay : garde anti double-dispatch fail-open quand Redis est indisponible
- **Fichier:ligne** : `app/Modules/Payroll/Interfaces/Api/V1/BulkPaymentController.php:83-87` ; `app/Jobs/ProcessBulkPaymentJob.php:135-146`
- **Snippet** :
```php
} catch (Throwable) {
    // Redis unavailable — allow dispatch to continue          // contrôleur : dispatch sans garde
...
// ProcessBulkPaymentJob :
} catch (Throwable $redisError) {
    Log::warning('ProcessBulkPaymentJob: Redis claim unavailable, processing without guard', ...);
    $claimed = true;                                          // ← slip traité SANS claim
```
- Le verrou atomique `SET NX EX` (`:63-80`) et le claim par slip sont corrects en régime nominal, mais les deux niveaux dégradent en mode « sans garde » quand Redis tombe : deux `POST bulk-pay` simultanés → deux jobs → paiements/documents dupliqués (mouvement d'argent).
- **Sévérité : P2**

### 11. Échec d'envoi OTP avalé → `POST /trial/signup` répond 200 « Code envoyé » mensonger
- **Fichier:ligne** : `app/Modules/Billing/Application/Actions/RequestTrialSignup.php:30-49` ; réponse : `app/Modules/Billing/Interfaces/Api/V1/SelfServiceTrialController.php:100-108`
- **Snippet** :
```php
$this->createPendingCompanyRequest($validated, $email, $otp);   // ligne 30 : requête créée AVANT le mail
try {
    Mail::to($email)->send(new TrialVerificationMail(...));
} catch (\Throwable $e) {
    Log::error(...);
    // Allow testing in local/staging without mailer failing the request   // ← avalé
}
```
- En prod, mailer KO → l'API répond quand même `success: true, 'Code de vérification envoyé.'` (200). La `CompanyRequest` reste `pending` avec un OTP que l'utilisateur ne recevra jamais ; `/trial/verify` répondra INVALID_OR_EXPIRED_CODE à un utilisateur légitime. (L'OTP est de plus stocké en clair : `verification_token` = OTP, `RequestTrialSignup.php:156-157`.)
- **Sévérité : P2**

### 12. Payout partenaire : aucune vérification de solde de commissions ni dédup des demandes pending
- **Fichier:ligne** : `app/Modules/Growth/Application/Actions/RequestPayout.php:15-33` ; entrée : `app/Modules/Growth/Interfaces/Api/V1/Controllers/PartnerDashboardController.php:67-87`
- **Snippet** :
```php
public function execute(Partner $partner, float $amount, string $method = 'bank_transfer'): PartnerPayoutRequest
{
    if ($amount <= 0) { throw new \InvalidArgumentException(...); }
    return DB::transaction(function () ... PartnerPayoutRequest::create([... 'status' => 'pending']));
}
```
- Validation : `amount >= 100` uniquement (`PartnerDashboardController.php:77`). Un partenaire peut empiler des demandes pending sans limite (aucune somme des `pending`, aucun plafond sur les commissions gagnées) ; deux POST simultanés créent deux demandes (pas de lock), et `updatePayoutStatus` (`app/Modules/Billing/Infrastructure/Services/PartnerService.php:173-194`) ne fait pas de transition conditionnelle `pending → paid`. Sur-paiement possible.
- **Sévérité : P2**

### 13. Candidature ATS : aucune protection anti-doublon (pas d'index unique, pas de vérification)
- **Fichier:ligne** : `app/Modules/Recruitment/Interfaces/Api/V1/CandidateApplicationController.php:46-58` ; schéma : `database/migrations/tenant/2026_05_10_000005_create_recruitment_tables.php:41-48`
- **Snippet** :
```php
$applicant = Applicant::create([
    'company_id' => $company->id, 'job_posting_id' => $job->id,
    'email' => $validated['email'], ... 'status' => 'new', 'applied_at' => now(),
]);
```
- La table `applicants` n'a aucun index unique sur (`job_posting_id`, `email`) : double-clic/retry/spam créent des doublons illimités (pollution du pipeline + volume stockage), même candidat admis deux fois.
- **Sévérité : P2** (race/spam sur endpoint public `throttle:public-careers`)

### 14. Messages d'erreur bruts exposés aux clients
- **Fichier:ligne** : `app/Core/Auth/Interfaces/Api/V1/AuthController.php:192` (`GOOGLE_AUTH_FAILED` → `$e->getMessage()`) et `:261` ; `app/Core/Auth/Interfaces/Api/V1/SSOController.php:117,119,134,154` ; `app/Modules/Payroll/Interfaces/Api/V1/PayrollRunController.php:228,283,311,344`
- **Snippet** :
```php
} catch (\Exception $e) {
    return new JsonResponse(['error' => 'GOOGLE_AUTH_FAILED', 'message' => $e->getMessage()], 422);
```
- Les exceptions internes (détails de stack, configs, dépendances IdP) sont renvoyées telles quelles aux clients HTTP. Le reste du code est mieux : `/trial/verify` normalise proprement (500 → 503 structuré).
- **Sévérité : P2** (info leak, aide à l'attaque)

---

## P3 — Fiabilité / drift routes↔contrôleurs / isolation

### 15. `liveMap` : 1 appel HTTP Traccar par véhicule (N+1 réseau)
- **Fichier:ligne** : `app/Modules/Fleet/Interfaces/Api/V1/FleetController.php:55-63` (`getLastPosition` = `Http::` dans `app/Modules/Attendance/Infrastructure/Services/TraccarService.php:76+`)
- **Snippet** :
```php
foreach ($vehicles as $vehicle) {
    $pos = $traccar->getLastPosition((int) $vehicle->traccar_device_id);   // ← 1 GET HTTP séquentiel / véhicule
    $positions[] = [...];
}
```
- Avec 100 véhicules actifs : 100 requêtes HTTP séquentielles dans une seule requête API (latence ≈ somme, timeouts possibles). Aucune concurrence ni endpoint batch Traccar.
- **Sévérité : P3**

### 16. `markPaid` (avances) : lazy-load de l'employé
- **Fichier:ligne** : `app/Modules/Payroll/Interfaces/Api/V1/SalaryAdvanceController.php:200`
- **Snippet** :
```php
employee: $salaryAdvance->employee ?? Employee::query()->find($salaryAdvance->employee_id),
```
- `$salaryAdvance` est route-model-bound sans `->load('employee')` : le `??` force un lazy-load (requête N+1) + requête de secours. S'applique aussi à `GeneratePaymentDocumentJob::dispatchForSalaryAdvance` et au ledger.
- **Sévérité : P3**

### 17. `per_page`/`limit` non bornés sur ~20 endpoints
- **Fichier:ligne** (représentatifs) : `app/Modules/Attendance/Interfaces/Api/V1/ApprovalController.php:116,203` ; `app/Modules/HR/Interfaces/Api/V1/Controllers/AuditLogController.php:61` ; `TrainingController.php:33,121,159,185` ; `ContractController.php:39` ; `EvaluationController.php:83` ; `SelfServiceController.php:74,113` ; `app/Modules/Fleet/Interfaces/Api/V1/VehicleController.php:36,186,199,212` ; `VehicleTripController.php:36` ; `BillingController.php:134` ; `WebhookController.php:261` ; `CabinetDocumentController.php:59` ; `app/Http/Controllers/AI/AIGatewayController.php:57`
- **Snippet** :
```php
->paginate($request->integer('per_page', 15));     // ← per_page=999999 accepté, pas de max()
```
- Aucune borne haute : `GET /approvals/pending?per_page=1000000` force une matérialisation massive. Contraste : plusieurs contrôleurs font bien `min(100, max(1, ...))` (ex. `SocialPostController.php:44`).
- **Sévérité : P3**

### 18. Routes notifications legacy toujours routées (verbes non canoniques)
- **Fichier:ligne** : `routes/modules/rh.php:175-177` (alias « rétro-compat »)
- **Snippet** :
```php
Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::put('/notifications/{notification}/read', ...);
Route::delete('/notifications/{notification}', ...);
```
- Canonique déclaré dans `routes/modules/dashboard.php:32-33` (`PATCH /notifications/{id}/read`, `POST /notifications/mark-all-read`). Deux verbes pour la même sémantique = drift de contrat mobile/web, ambiguïté de cache/routage.
- **Sévérité : P3**

### 19. Méthode morte : `TrainingController::indexSessionsAll` jamais routée (doublon de `indexAllSessions`)
- **Fichier:ligne** : `app/Modules/HR/Interfaces/Api/V1/Controllers/TrainingController.php:142-163` (morte) vs `:112-124` (routée, `routes/modules/hr_extended.php:126`)
- **Snippet** :
```php
public function indexSessionsAll(Request $request): JsonResponse   // ← jamais référencée dans routes/
```
- Deux implémentations quasi identiques de « toutes les sessions » ; `indexSessionsAll` n'est référencée par aucune route (grep routes/ : 0). Maintenance : les correctifs ne seront appliqués qu'à l'une des deux.
- **Sévérité : P3**

### 20. `LeavePolicyController` dupliqué dans 2 modules (Absence + Planning)
- **Fichier:ligne** : `app/Modules/Absence/Interfaces/Api/V1/Controllers/LeavePolicyController.php` (35 lignes, `balances`) et `app/Modules/Planning/Interfaces/Api/V1/LeavePolicyController.php` (261 lignes, CRUD complet) ; les deux sont routés (`routes/modules/absence.php:31-34`, `routes/modules/hr_extended.php:82`)
- Deux classes du même nom dans deux modules, avec des routes de concepts voisins (`/employees/{employeeId}/leave-balances` vs `/leave-balances`) et des gardes différentes (voir constat 1). Risque de collision de résolution et de divergence de comportement.
- **Sévérité : P3** (le manque de garde qui en découle est le P1 n°1)

### 21. Throttles absents : routes SSO publiques et `platform/growth`
- **Fichier:ligne** : `routes/modules/sso.php:12-17` (publiques, zéro throttle) ; `routes/modules/growth.php:22` (`auth:super_admin_api` sans `throttle:platform-sensitive`)
- **Snippet** :
```php
Route::get('/sso/providers', [SSOController::class, 'providers']);                      // public, sans throttle
Route::post('/sso/saml/{companyId}/callback', [SSOController::class, 'samlCallback']);  // public, sans throttle
Route::middleware(['auth:super_admin_api'])->prefix('platform/growth')->group(...)      // admin sans throttle
```
- Les callbacks SAML/OIDC (endpoints publics qui reçoivent des réponses IdP) sont martelables sans limite (bruteforce/DoS/abus), contrairement aux autres groupes publics (`throttle:webhooks-inbound`, `throttle:public-careers`, `throttle:10,1`, `throttle:5,15`, `throttle:trial-status`). Tous les groupes `/platform/*` utilisent `throttle:platform-sensitive`, sauf `platform/growth`.
- **Sévérité : P3**

### 22. `company_id` nullable sur les données métier — isolation reposant sur la discipline du scope global (silencieusement sauté si tenant non bound)
- **Fichier:ligne** : 41 occurrences « `company_id` nullable » ex. `database/migrations/tenant/2026_04_01_000100_create_departments_positions_schedules_sites.php:31,46,58,78`, `2026_04_01_000101_create_employees_table.php:28` (commentaire « NULL en mode schema isolé ») ; mécanique : `app/Shared/Traits/BelongsToCompany.php:26-46`
- **Snippet** :
```php
if (! $currentCompany instanceof Company) { return; }   // ← scope GLOBAL silencieusement ignoré
```
- Choix délibéré (mode schema isolé), mais en mode `shared` (le mode réellement provisionné : `schema_name = 'shared_tenants'`, cf. `VerifyTrialSignup.php:230`) la séparation cross-tenant dépend de chaque requête. Dès qu'un contrôleur omet le `where('company_id')` (constat 1) ou que `current_company` n'est pas bound (jobs, console, employé `ordinary`), le scope ne s'applique pas **sans aucun échec** : lecture cross-tenant silencieuse. Un échec fort (exception si tenant absent) serait plus sûr que le return silencieux.
- **Sévérité : P3** (facteur aggravant du P1 n°1)

### 23. Drift spec ↔ code : 119 chemins de code absents de `openapi.yaml` (reconnu en interne)
- **Fichier:ligne** : `docs/qa/QA_SESSION_2026-08-15.md` (API-6, #2638) ; spec : `api/openapi.yaml`
- Constat interne déjà documenté par l'équipe : 119 chemins implémentés ne figurent pas dans la spec OpenAPI (ex. zones `/admin/*`, `/ai/analytics/*`, `/platform/growth/*`). Vérifié ponctuellement : les routes existent dans `routes/` mais absentes de la spec → clients générés depuis la spec inopérants.
- **Sévérité : P3**

---

## Points de conformité vérifiés (pas d'anomalie)

- **Migrations tenant** : 108 fichiers dans `database/migrations/tenant/` (ex. `2026_04_24_000111_create_cameras_module_tables.php`), exécutées sur le schéma tenant actif — conforme à la spec kit.
- **`/trial/verify`** : verrou `lockForUpdate` anti double-provisioning présent et correct (`VerifyTrialSignup.php:78-99`), avec statut 409 `ALREADY_PROCESSED` ; exceptions de provisioning normalisées en 503 (`SelfServiceTrialController.php:179-196`).
- **Bulk-pay** : garde atomique `SET NX EX` en régime nominal + claims par slip + idempotence par claim (`BulkPaymentController.php:63-80`, `ProcessBulkPaymentJob.php:117-146`) — le trou est le fail-open Redis (constat 10).
- **Throttle public** : `/trial/*`, `/onboarding/invitation/*`, `/marketing/leads`, `/webhooks/*`, `/public/careers/*`, `/demo-users` ont tous des limiters dédiés (`AppServiceProvider.php:81-226`).
- **CameraPolicy** : `view/update/delete/testRtsp` vérifient `company_id` + rôles (`app/Policies/Cameras/CameraPolicy.php`).
- **Magic link démo** : désormais réellement émis (fix #2629) — l'anomalie restante est le double envoi (constat 6).
- **Onboarding-setup PATCH vs POST** : drift MOB-1 (#2631) corrigé — les 3 apps mobiles utilisent désormais PATCH comme le backend (`front/mobile_apps/*/lib/features/onboarding/data/onboarding_repository.dart:27,37`).

---

## Résumé — 15 constats les plus solides

| # | Sév. | Constat | Où |
|---|------|---------|-----|
| 1 | P1 | IDOR `leave-balances` sans `company_id` ni rôle (route Absence) | `routes/modules/absence.php:31` ; `app/Modules/Absence/.../LeavePolicyController.php:16-29` |
| 2 | P1 | OAuth Google : employé `ordinary` tenantless + token ; bypass tenant | `AuthController.php:196-222` ; `TenantMiddleware.php:51-52` |
| 3 | P1 | ApprovalRequestPolicy jamais invoquée ; approve/reject sans garde manager | `ApprovalController.php:121-134` ; `hr_extended.php:55-58` |
| 4 | P1 | SSRF ffprobe `rtsp://` sans blocklist | `CameraService.php:380-392` |
| 5 | P1 | Clé QR fallback hardcodée (fail-open) | `OnboardingQrService.php:144-148` |
| 6 | P2 | Magic link émis 2× (1er token invalidé) | `ProvisionDemoTenantJob.php:42,63` |
| 7 | P2 | `/trial/verify` : `days:30` vs `ends_at:+14`, « mot de passe ci-dessus » absent | `SelfServiceTrialController.php:219-224` |
| 8 | P2 | `trial_days` plan ignoré (14 jours hardcodés 2 chemins) | `VerifyTrialSignup.php:235` ; `ProvisionGuidedTrial.php:56` |
| 9 | P2 | Essai guidé : mot de passe jamais communiqué, `status=ready` sans accès | `ProvisionGuidedTrial.php:64-66` ; `ProvisionDemoTenantJob.php:47-54` |
| 10 | P2 | Bulk-pay fail-open si Redis down (double paiement) | `BulkPaymentController.php:83-87` ; `ProcessBulkPaymentJob.php:135-146` |
| 11 | P2 | OTP mail KO avalé → 200 « Code envoyé » mensonger ; OTP en clair | `RequestTrialSignup.php:30-49,156` |
| 12 | P2 | Payout : pas de plafond commissions ni dédup pending | `RequestPayout.php:15-33` ; `PartnerService.php:173-194` |
| 13 | P2 | Candidature : pas d'index unique ni dédup (doublons illimités) | `CandidateApplicationController.php:46-58` ; migration `...create_recruitment_tables.php:41-48` |
| 14 | P2 | Exceptions brutes exposées (`$e->getMessage()`) | `AuthController.php:192` ; `SSOController.php:117,119,134,154` ; `PayrollRunController.php:228,283,311,344` |
| 15 | P3 | N+1 réseau liveMap (1 appel Traccar/véhicule) ; per_page non bornés (~20 endpoints) | `FleetController.php:59` ; `ApprovalController.php:116` etc. |

(Constats additionnels P3 dans le corps : 15-23 — legacy notifications `rh.php:175-177`, méthode morte `TrainingController.php:142`, `LeavePolicyController` dupliqué, throttles SSO/growth manquants, `company_id` nullable, drift OpenAPI 119 chemins.)

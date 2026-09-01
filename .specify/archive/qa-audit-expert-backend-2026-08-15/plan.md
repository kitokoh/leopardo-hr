# Plan: Audit Expert Backend — Hardening Sécurité & Onboarding — 2026-08-15

**Input**: spec.md (US1-US5) + Constitution + registre project-state

## Architecture / Décisions techniques

### US1 — Webhooks fail-closed (P1)
- `StripeService::verifyWebhookSignature` (`app/Modules/Billing/Infrastructure/Services/StripeService.php:122-125`) : remplacer le `return json_decode($payload, true)` par `Log::error` + `return null` — le controller (`StripeWebhookController`) renvoie déjà 400 quand null. Décision : **retourner null** (pas de throw) pour réutiliser le chemin d'erreur existant et éviter un 500 ; le 503 n'est pas nécessaire si le 400 est explicite. Alternative rejetée : `abort(503)` — le controller existant gère déjà `null` proprement.
- `ChargilyService::verifyWebhookSignature` (`ChargilyService.php:33-35`) : même traitement.
- `EmailBounceWebhookController` (`app/Modules/Notification/Interfaces/Api/V1/Controllers/EmailBounceWebhookController.php:32-43`) : si secret non configuré → `abort(503, 'Webhook secret not configured')` ; si signature invalide → 400 (déjà le cas).
- Tests : `tests/Feature/Webhook*` + tests unitaires services (secret absent → null ; secret présent → validation).

### US2 — Durcissement auth (P1)
- **Register** : `RegisterAction` (`app/Core/Auth/Application/Actions/RegisterAction.php`) — nouvelle règle : si `data['invitation_token']` présent → rattacher au `company_id` de l'invitation (via `UserInvitationService`), sinon refus `422 REGISTRATION_NOT_AVAILABLE`. `StoreRegistrationRequest` valide `invitation_token` nullable. L'écran mobile `/register` reçoit un message clair. Décision : **ne pas** créer de compagnie implicite (side-effect non maîtrisé).
- **Suspended** : `UserAuthService::login()` + `googleSignIn()` — après lookup, `if ($user->status !== 'active') throw new AccountSuspendedException` (existe déjà, `app/Exceptions/AccountSuspendedException.php`). Vérifier comment l'exception est mappée en HTTP (handler) — 403 attendu. Nota : `User::status` a des valeurs documentées (`User.php:30`).
- **OAuth state** : `AuthController::redirectToGoogle` (`app/Core/Auth/Interfaces/Api/V1/AuthController.php:160`) — générer `state` aléatoire (64 hex) en session, le passer à `Socialite::with('google')->with(['state' => ...])` ; `handleGoogleCallback` (:166) — comparer le state de la requête avec la session, `abort(400)` si mismatch. Le flux mobile `handleGoogleToken` (:205, access_token direct) n'est pas concerné.

### US3 — Onboarding (P2)
- **Magic link** : `ProvisionDemoTenantJob::handle()` — après succès du provisioning, récupérer le manager depuis `$result` (vérifier le retour de `ProvisionGuidedTrial::execute` — clé `manager` ou `employee`) et appeler `issueDemoAccess($manager)` (déjà implémenté, `ProvisionDemoTenantJob.php:76-...`). Le test `ProvisionDemoTenantJobTest` (assert hash + mail) redevient vert.
- **Throttle trial/status** : sortir `GET /trial/status` du groupe `throttle:5,15` (`api/routes/api.php:98-101`) → groupe dédié `throttle:trial-status` défini dans `app/Http/Kernel.php` (ex. `60,1`), conservant `signup`/`verify` en 5,15. L'UI poll toutes les 5 s (12 tentatives) = ≤ 12 req/min → OK.

### US4 — Cohérence API (P2)
- **Growth tenant** : `routes/modules/growth.php:10-16` — ajouter `tenant` au middleware du groupe `partner` (avec `auth:sanctum`), comme les autres modules. Vérifier que le controller lit `company_id` depuis le tenant courant.
- **Calendar scoping** : migration tenant `database/migrations/tenant/XXXX_add_company_id_to_calendar_connections_events.php` + modèles `CalendarConnection`/`CalendarEvent` (trait `BelongsToCompany` ou colonne + boot). Vérifier la table (dans `shared_tenants`) et l'écriture dans `CalendarIntegrationService`.
- **Impersonations admin** : ajouter sous le groupe `/admin` (`api/routes/api.php:222`) `POST /admin/impersonations` + `GET /admin/impersonations` + `DELETE /admin/impersonations/{session}` réutilisant `PlatformImpersonationController` (ou un alias de routes avec le même controller).
- **Doublon route** : `routes/modules/hr_extended.php:168` vs `:176` — supprimer le doublon (garder la définition complète avec `whereNumber`).

### US5 — Password reset (P3)
- Routes : `POST /auth/forgot-password` (email → token 60 min via table `password_reset_tokens` tenant-scoped ou table dédiée) + `POST /auth/reset-password` (token + nouveau mdp → hash + suppression token + révocation tokens existants). Controller dédié `PasswordResetController` + mail `PasswordResetMail`. Réponse générique 200 (anti-énumération). Tests complets (token invalide/expiré/usage unique).

## Phases

### Phase 1 — US1+US2 sécurité (P1)
- T001 Fail-closed Stripe + test
- T002 Fail-closed Chargily + test
- T003 Fail-closed email-bounce + test
- T004 Register invitation-only + test
- T005 Suspended login (email + Google) + test
- T006 OAuth state (redirect + callback) + test
- Checkpoint : `php artisan test --filter="Webhook|Auth"` vert + phpstan strict 0 erreur + pint 0 fichier.

### Phase 2 — US3 onboarding (P2)
- T007 Magic link : appeler issueDemoAccess + ProvisionDemoTenantJobTest vert
- T008 Limiteur dédié GET /trial/status + test

### Phase 3 — US4 cohérence API (P2)
- T009 Middleware tenant growth/partner + test isolation
- T010 Calendar company_id (migration + modèles + service) + test
- T011 Routes impersonations sous /admin + test
- T012 Suppression doublon webhooks/{id}/test

### Phase 4 — US5 password reset (P3)
- T013 Forgot/reset password + mail + tests

## Validation finale
`php artisan test --testsuite=Unit` + `--testsuite=Feature` (ciblés), `vendor/bin/phpstan analyse --configuration=phpstan-strict.neon`, `vendor/bin/pint --test`, entrée CHANGELOG.

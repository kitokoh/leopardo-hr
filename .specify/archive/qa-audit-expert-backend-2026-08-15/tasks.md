# Tasks: Audit Expert Backend — Hardening Sécurité & Onboarding — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — US1+US2 Sécurité webhooks & auth (P1) — issues #2614-#2619

- [x] T001 [P] [US1] Fail-closed Stripe : `StripeService::verifyWebhookSignature` retourne null (log error) quand `webhookSecret` vide (`app/Modules/Billing/Infrastructure/Services/StripeService.php:122-125`) + test unitaire service (secret vide → null, signature OK → payload, mismatch → null)
- [x] T002 [P] [US1] Fail-closed Chargily : même traitement (`ChargilyService.php:33-35`) + test unitaire
- [x] T003 [P] [US1] Fail-closed email-bounce : `abort(503)` si secret non configuré (`app/Modules/Notification/Interfaces/Api/V1/Controllers/EmailBounceWebhookController.php:32-43`) + test Feature (503 sans secret, 200 avec secret+signature valide, 400 invalide)
- [x] T004 [P] [US2] Parcours register→login réparé : `AuthService::login()` autorise les comptes ordinary sans company (token confiné pré-tenant, avant : `CompanyNotFoundException` → login impossible) ; le register public reste le point d'entrée du self-onboarding company-request (`app/Core/Auth/Infrastructure/Services/AuthService.php`) + `RegisterLoginFlowTest` (register 201, re-login 200, suspended 403)
- [x] T005 [P] [US2] Suspended login : `UserAuthService::login()` + `googleSignIn()` → `AccountSuspendedException` si `status !== 'active'` (`app/Core/Auth/Infrastructure/Services/UserAuthService.php:34-52,72-108`) + tests unitaires (email + google)
- [x] T006 [P] [US2] OAuth Google state : state aléatoire en session dans `redirectToGoogle` + validation dans `handleGoogleCallback` (`app/Core/Auth/Interfaces/Api/V1/AuthController.php:160,166`) + test Feature (callback sans state → 400, avec state valide → redirection/login OK)

**Checkpoint**: `php artisan test --filter="Webhook|Auth|Register|Password"` vert + phpstan-strict 0 erreur + pint 0 fichier.

## Phase 2 — US3 Onboarding trial (P2) — issues #2620-#2621

- [x] T007 [US3] Magic link : `ProvisionDemoTenantJob::handle()` appelle `issueDemoAccess($manager)` après provisioning réussi (`app/Jobs/ProvisionDemoTenantJob.php:51`) — vérifier clé manager dans `ProvisionGuidedTrial::execute` ; `ProvisionDemoTenantJobTest` (hash token + mail `/demo-login/`) redevient vert
- [x] T008 [US3] Limiteur dédié : sortir `GET /trial/status` du groupe `throttle:5,15` (`api/routes/api.php:98-101`) vers `throttle:trial-status` (60,1) dans `app/Http/Kernel.php` + test Feature (20 polls / min sans 429)

## Phase 3 — US4 Cohérence API (P2) — issues #2622-#2625

- [x] T009 [P] [US4] Middleware `tenant` sur le groupe `growth/partner` (`routes/modules/growth.php:10-16`) + test isolation cross-tenant (404)
- [x] T010 [P] [US4] Calendar OAuth scopé : migration tenant ajoutant `company_id` à `calendar_connections`/`calendar_events` + modèles `CalendarConnection`/`CalendarEvent` (`app/Modules/Attendance/Domain/Models/`) + écriture dans le service d'intégration + test
- [x] T011 [P] [US4] Endpoints impersonation sous `/admin` : `POST /admin/impersonations` (+ index/delete) réutilisant `PlatformImpersonationController` (`api/routes/api.php:222` vs `:284-286`) + test Feature (201 super-admin, 403 non-admin)
- [x] T012 [P] [US4] Supprimer le doublon `POST /webhooks/{webhookEndpoint}/test` (`routes/modules/hr_extended.php:168` vs `:176`) + test liste des routes (une seule occurrence)

## Phase 4 — US5 Password reset (P3) — issue #2626

- [x] T013 [US5] `POST /auth/forgot-password` + `POST /auth/reset-password` : controller `PasswordResetController`, mail `PasswordResetMail`, token 60 min usage unique, réponse générique anti-énumération, révocation des tokens Sanctum existants + tests Feature (demande, reset valide, token expiré/réutilisé → 422)

## Dependencies & Execution Order

- Phase 1 ne bloque que les PRs backend sécurité ; Phase 2 dépend de rien ; Phase 3 T011 dépend de l'emplacement du groupe `/admin` (lecture seule) ; Phase 4 indépendante.
- PR : `fix/qa-<n>-backend-hardening` — avec `Closes #<issues>`.
- Ne pas toucher aux flux OIDC/SSO existants (feature `oidc-flow` en cours ailleurs).

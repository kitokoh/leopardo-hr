# Plan: Vague QA Complète 2026-08-15 — Vitrine, Web, Admin, Mobile, API, Onboarding

**Input**: spec.md (US1-US6) + Constitution + constats live/statiques (session 2026-08-15)

## Architecture / Décisions techniques

### Backend (api/)

- **Checkout (US1, #2628)** : la décision sandbox est côté Next (route handler). Garde
  stricte : `isSandbox = process.env.SANDBOX_CHECKOUT === 'true'` — plus jamais de
  déduction depuis `!STRIPE_SECRET_KEY`. Sans clé Stripe et sans flag sandbox → 503
  `CHECKOUT_UNAVAILABLE`. La route `/api/v1/billing/checkout` (backend) reste le chemin
  réel du paiement.
- **Trial guidé (US2, #2629)** : `ProvisionDemoTenantJob` — remplacer le TODO par
  l'appel à `issueDemoAccess()` (ou un email dédié via `Mail`/`Notification`) contenant
  un magic link (token à usage unique lié au manager provisionné, route
  `/onboarding/invitation/{token}` ou `/auth/magic-link` existante si compatible) ;
  `ProvisionGuidedTrial` génère un token récupérable ; `trial/status` n'expose `ready`
  que si `provisioned_at && access_sent_at`.
- **Auth statut (US3, #2630)** : centraliser dans un trait/service `AssertActiveAccount`
  (status employé `users.status`, société active, super-admin actif) utilisé par
  `UserAuthService::login/googleSignIn`, `PlatformAuthController::login` et les 2
  handlers Google. Révocation : à la suspension (PlatformUserController), `delete()` des
  tokens Sanctum du user (`$user->tokens()->delete()`). Erreur normalisée
  `ACCOUNT_SUSPENDED` (403) — suivre le format d'erreur existant (error/message/
  localized_message).
- **Organigramme (US4, #2633)** : nouveau contrôleur `DepartmentHierarchyController` ou
  méthode sur le contrôleur existant — arbre récursif `departments.parent_id` (children
  + `employees_count`), scope `company_id`, middleware `api.manager`. Route
  `GET /api/v1/departments/{department}/hierarchy` dans `routes/modules/rh.php` (groupe
  tenant existant).
- **Console admin (US5, #2634)** : étendre le groupe `/admin` de `routes/api.php` avec
  `/admin/training/sessions`, `/admin/training/enrollments`, `/admin/webhooks` (CRUD),
  `/admin/webhooks/{id}/test` — contrôleurs adossés aux mêmes services que les routes
  tenant, policy `super_admin_api` (pattern existant du groupe admin). Ne PAS toucher
  aux routes tenant.
- **Middleware tenant (US3, #2635)** : ajouter `tenant` (+ `token.refresh`,
  `throttle:api-plan` selon le groupe standard) sur `routes/ai.php` et
  `routes/modules/growth.php` ; aligner `routes/modules/sso.php` sur le groupe standard.
  Vérifier que les contrôleurs AI supportent le scope tenant réel (pas de régression
  sur `AITenantInjector`).
- **/auth/register (US3, #2636)** : supprimer la route publique (le parcours officiel
  est `/user/register` + company-request / invitation) ou la verrouiller (403
  `REGISTRATION_DISABLED`). Supprimer l'action orpheline si plus référencée.
- **Invitations (US3, #2637)** : dans `UserInvitationService::accept()`, vérifier
  `$company->status === 'active'` avant activation ; 403 `COMPANY_SUSPENDED` sinon.

### Web / vitrine (front/web/)

- **Checkout UI (US1, #2628)** : prop/env `NEXT_PUBLIC_CHECKOUT_SANDBOX` pour afficher
  la carte test seulement en sandbox ; état d'erreur 503 avec message clair ; le bouton
  « Démarrer l'essai » reste fonctionnel si le backend trial est joignable.
- **Robots (US6, #2643)** : retirer la ligne `Sitemap: …/api/sitemap` de
  `app/api/robots/route.ts` ; vérifier la redondance avec `app/robots.ts` (supprimer la
  route dupliquée si non utilisée).
- **i18n (US6, #2642, volet minimal)** : router les pages FR-only sur
  `useVitrineLocale` (fallback FR), localiser `OnboardingWizard` via les locales
  dashboard existantes.

### Admin (front/admin-dashboard/)

- **Training/Webhooks (US5, #2634)** : basculer `TrainingView`/`WebhooksView` sur les
  routes `/admin/*` ; garder le fallback tenant si l'utilisateur est manager.
- **Handlers réels (US5, #2641)** : `EditUserModal` → endpoints `/admin/users/{id}`
  (PATCH), reset password via endpoint admin existant ou état « non disponible » ;
  `SystemAlertsOverlay` → endpoint réel de maintenance (ou suppression du faux toggle).
- **Palette/i18n (US5, #2640, #2639)** : corriger les cibles de la palette, filtrer les
  routes tenant pour super-admin, traduire `meta.title`, ajouter les 2 clés manquantes.

### Mobile (front/mobile_apps/)

- **Onboarding (US4, #2631)** : `leopardo_employee` + `leopardo_hr`
  `onboarding_repository.dart` — `method: 'PATCH'` et `stepKey` (string) au lieu de
  l'id ; aligner sur `leopardo_manager` (déjà correct).
- **Organigramme (US4, #2633)** : aucune modif client nécessaire si la route backend
  respecte le contrat attendu (`data: { department, children: [...] }`) — vérifier le
  parsing Dart avant de figer le contrat.

### Ops (US—, #2632)

- Documenter l'état des 3 déploiements (Render stale, Pages stale, Vercel failed) dans
  `docs/qa/QA_SESSION_2026-08-15.md` ; PR possible : healthcheck de version
  (`/api/v1/health` + build marker) pour détecter les déploiements stale à l'avenir.
  Le redéploiement effectif reste une action manuelle/ops hors périmètre code.

## Risques

- **Conflits routes** : T010/T013/T014/T015 touchent `api/routes/` → une PR par issue,
  branches `fix/<issue>-<slug>`, rebase sur main avant push (protocole #2400).
- **Régression checkout** : la garde sandbox doit rester fonctionnelle en dev
  (documenter `SANDBOX_CHECKOUT=true` dans `.env.example`).
- **Google auth** : les gardes `AuthService` exigent un employé scopé — vérifier le
  comportement `withoutGlobalScopes` actuel avant de brancher les checks (ne pas casser
  le SSO employé).
- **Flutter** : pas de run local — les changements Dart sont minimaux et vérifiés par
  analyse statique + CI mobile.

## Validation

- Backend : `php artisan test` ciblé + `phpstan-strict.neon` (level 8) + `pint --test`.
- Front web : `npm run lint` + `tsc --noEmit` + build.
- Admin : `npm run lint` + build.
- Mobile : CI Flutter (analyze + tests).

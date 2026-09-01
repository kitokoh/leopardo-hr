# Tasks: Vague QA Complète 2026-08-15 — Vitrine, Web, Admin, Mobile, API, Onboarding

**Input**: spec.md + plan.md + issues #2628–#2645

**Prerequisites**: plan.md (required), spec.md (required)

**Issues couvertes** : #2628 (checkout sandbox) · #2629 (trial guidé) · #2630 (auth
statut) · #2631 (mobile onboarding 405) · #2632 (déploiements stale) · #2633
(organigramme hierarchy) · #2634 (admin training/webhooks /admin) · #2635 (middleware
tenant /ai + /growth) · #2636 (/auth/register orphelin) · #2637 (invitation société
suspendue) · #2638 (drift OpenAPI) · #2639 (i18n admin) · #2640 (command palette) ·
#2641 (handlers factices) · #2642 (pages FR-only) · #2643 (robots /api/sitemap) ·
#2644 (updateCountry dupliqué) · #2645 (orphelins admin)

## Phase 1 — P1 : Monétisation & Onboarding (US1, US2)

- [ ] T001 [P1] US1 Checkout : `front/web/src/app/api/billing/checkout/route.ts` —
      sandbox uniquement si `SANDBOX_CHECKOUT=true` ; sinon 503 `CHECKOUT_UNAVAILABLE`
      (Closes #2628). Exposer `NEXT_PUBLIC_CHECKOUT_SANDBOX` au client pour l'UI.
- [ ] T002 [P1] US1 Checkout UI : masquer « Remplir avec la carte test » / carte 4242
      hors mode sandbox (`checkout/page.tsx`), message d'erreur 503 propre.
- [ ] T003 [P1] US2 Trial guidé : câbler `ProvisionDemoTenantJob::handle()` — envoi du
      magic link via `issueDemoAccess()` (ou équivalent email) ; supprimer le TODO et
      `login_url` hardcodé (Closes #2629).
- [ ] T004 [P1] US2 Trial guidé : `ProvisionGuidedTrial.php` — communiquer le mot de
      passe provisoire (email) ou générer un magic link ; `trial/status` ne dit `ready`
      que si credentials communicables. Test d'intégration du parcours complet.

## Phase 2 — P1 : Auth & Mobile (US3, US4)

- [ ] T005 [P1] US3 `UserAuthService::login()/googleSignIn()` : vérifier `status ===
      'active'` → 403 `ACCOUNT_SUSPENDED` (Closes #2630).
- [ ] T006 [P1] US3 `PlatformAuthController` : vérifier `super_admins.status` ; les
      endpoints deactivate/suspend révoquent les tokens Sanctum du compte.
- [ ] T007 [P1] US3 `AuthController::handleGoogleCallback/handleGoogleToken` :
      réutiliser les gardes `AuthService` (statut employé + société).
- [ ] T008 [P1] US3 Tests : 4 chemins de login avec compte suspendu → 403 ; token
      existant suspendu → 401.
- [ ] T009 [P1] US4 `leopardo_employee` + `leopardo_hr`
      `onboarding_repository.dart` : `POST` → `PATCH`, param `stepKey` string
      (Closes #2631).
- [ ] T010 [P2] US4 Backend : route `GET /departments/{department}/hierarchy`
      (scopée tenant, `api.manager`) + contrôleur (arbre récursif enfants + effectif)
      + test cross-tenant 404 (Closes #2633).

## Phase 3 — P2 : Console admin & Sécurité (US5)

- [ ] T011 [P2] US5 Backend : routes `/admin/training/sessions`,
      `/admin/training/enrollments`, `/admin/webhooks*` (CRUD + test) avec policy
      admin ; tests super-admin 200 / tenant 401 (Closes #2634).
- [ ] T012 [P2] US5 Front : `TrainingView.vue`, `WebhooksView.vue` pointent vers les
      routes `/admin/*`.
- [ ] T013 [P2] US3 Middleware : ajouter le middleware tenant complet sur
      `routes/ai.php` + `routes/modules/growth.php` ; aligner `routes/modules/sso.php`
      (token.refresh, throttle api-plan) (Closes #2635).
- [ ] T014 [P2] US3 `/auth/register` : supprimer la route ou bloquer la création sans
      company_id (403 explicite) ; éviter le probing cross-tenant (Closes #2636).
- [ ] T015 [P2] US3 `UserInvitationService::accept()` : vérifier le statut société
      (Closes #2637).
- [ ] T016 [P2] US5 Admin : brancher `EditUserModal` (update, reset password, welcome
      email, force logout) + `SystemAlertsOverlay` (maintenance) sur des endpoints
      réels, sinon état « non disponible » explicite (Closes #2641).
- [ ] T017 [P3] US5 Admin : `CommandPalette` — vehicles → /fleet, settings → /settings,
      filtrer entrées tenant (Closes #2640) ; clés i18n manquantes + `document.title`
      traduit (Closes #2639).
- [ ] T018 [P3] US5 Admin : orphelins — brancher ou supprimer `useNotificationStream`,
      16 composants non importés, Alt+R (Closes #2645).

## Phase 4 — P3 : Cohérence docs/code & vitrine (US6)

- [ ] T019 [P3] US6 robots : supprimer la ligne `/api/sitemap` (ou la route dupliquée)
      (Closes #2643).
- [ ] T020 [P3] US6 `updateCountry` : dé-dupliquer le corps dans
      `PlatformCompanyController` (Closes #2644).
- [ ] T021 [P3] US6 OpenAPI : documenter les blocs manquants (priorité : /admin/*,
      /trial/*, /onboarding/invitation/*, /webhooks/*, /health/*) ; garde
      `check-openapi-route-coverage.py` à 0 (Closes #2638, volet code).
- [ ] T022 [P3] US6 Vitrine : router `/contact`, `/docs`, `/guides/*` sur
      `useVitrineLocale` (fallback FR) ; localiser l'OnboardingWizard (Closes #2642,
      volet minimal).

## Phase 5 — Ops (issue #2632)

- [ ] T023 [P1] Déploiements : documenter l'état stale (Render /api-explorer 500,
      Pages login href="#", Vercel deploy 06:23Z failed) + redéployer + vérifier
      (Closes #2632). Action manuelle/ops — PR si un script de vérification peut être
      ajouté (healthcheck version).

## Dependencies & Execution Order

- Phase 1 (US1/US2) et Phase 2 (US3/US4) sont indépendantes et prioritaires (P1).
- Phase 3 (P2) dépend du backend pour T011/T012 (routes /admin avant le front).
- Phase 4 (P3) indépendante.
- T010, T013, T014, T015 touchent tous `api/routes/` → attention aux conflits de
  branches (une PR = une issue, marker branch `fix/<issue>-<slug>`).

## Notes

- [P] = parallélisable (fichiers disjoints).
- Chaque tâche → PR unique avec `Closes #<issue>` ; CHANGELOG.md mis à jour.
- Backend : PHPStan strict level 8 + Pint + tests ciblés avant merge (Constitution §IV).
- Front : ESLint + tsc verts. Mobile : analyse statique (pas de run Flutter ici).

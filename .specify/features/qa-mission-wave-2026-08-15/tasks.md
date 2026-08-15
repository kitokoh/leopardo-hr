# Tasks: Vague Mission QA 2026-08-15 — manquements NOUVEAUX (T135+, dédupliqués)

**Input**: spec.md (vague mission QA 2026-08-15)

**Prerequisites**: spec.md (required)

> **Anti-doublon (Constitution §VII + #2400)** : T001-T134 couverts par les vagues
> parallèles (qa-audit-expert-*, qa-omnichannel-*, qa-mission-exhaustive). Cette liste
> ne contient que des findings vérifiés NON couverts par une issue/branche existante
> au moment de l'audit (2026-08-15, main d30b52da). Numérotation continue à partir de T135.

## Phase 1 — Vitrine web (US1)

- [ ] T135 [P1] [US1] `front/web/src/modules/vitrine/components/PricingSection.tsx:14-21` : `getPlanCtaHref` mappe le prix `'0'` → `planKey='starter'` → checkout Pilot payant. Aligner sur `data/pricing.ts` (plan `free` → `plan=free`), comme la page `/pricing`.
- [ ] T136 [P1] [US1] og:image 404 : `front/web/src/modules/vitrine/lib/seo.ts:94-341` référence `/og/landing.png`, `/og/employes.png`, … (19 fichiers) absents de `public/` (seul `default.png` existe). Générer les images (ou rewrite `next.config.ts` vers `default.png` documenté).
- [ ] T137 [P2] [US1] `front/web/src/app/opengraph-image.tsx:98-100` : retirer les métriques fabriquées (« 500+ entreprises accompagnees », « 50K+ employes geres », « 99.9% disponibilite ») — cohérent avec `SocialProofMetrics.tsx` (PA2-MKT-006).
- [ ] T138 [P2] [US1] `front/web/src/modules/vitrine/components/sections/BlogArticle.tsx:242` : liens tags `/blog?category=${tag}` morts (la page blog ne lit pas `category` ; valeurs tags ≠ categories). Filtrer réellement ou retirer le lien.
- [ ] T139 [P2] [US1] `front/web/src/app/sitemap.ts:5` : fallback `https://gestionemployer-backend.vercel.app` → `NEXT_PUBLIC_SITE_URL`/`site-url.ts` (domaine de marque).
- [ ] T140 [P2] [US1] `front/web/src/modules/vitrine/lib/seo.ts:156` : meta description pricing FR en dur (aucune clé `seo.pricing.description` dans les 4 catalogues) → i18n complète.
- [ ] T141 [P3] [US1] `front/web/src/modules/vitrine/data/changelog-public.ts:14` : périmé (4.16.59 vs 4.24.0) → synchroniser avec CHANGELOG.md ou générer.
- [ ] T142 [P3] [US1] `front/web/src/app/(landing)/integrations/page.tsx:221` : lien `/docs` en dur vers Render → proxy same-origin `/api/v1`.
- [ ] T143 [P3] [US1] `front/web/public/sw.js:13-16` : précache de `/dashboard`, `/attendance`… (routes protégées, middleware → login) + sync tags `sync-forms`/`sync-analytics` (PWAProvider.tsx:119,125) jamais écoutés par sw.js.
- [ ] T144 [P3] [US1] `front/web/src/app/(landing)/download/page.tsx:89,124,159,194` : plan fantôme « Starter » dans la FAQ (4 locales) → Free/Pilot/Operations/Enterprise.
- [ ] T145 [P3] [US1] `front/web/src/app/(landing)/branding/page.tsx:106-123` : plans fantômes « Starter / Pro » → grille réelle.
- [ ] T146 [P3] [US1] `front/web/src/app/(dashboard)/layout.tsx:417-418` : FeatureLockedPanel FR en dur malgré sélecteur de langue → `useVitrineLocale`/i18n.
- [ ] T147 [P3] [US1] `front/web/src/components/StickyMobileCTA.tsx:120` : aria-label « Fermer » FR en dur → localisé.

## Phase 2 — Admin dashboard (US2)

- [ ] T148 [P2] [US2] `front/admin-dashboard/src/views/users/UsersView.vue:314-318` vs `components/users/UserTable.vue:116,121,189,208` : mapping camelCase vs lecture snake_case (`company_name`, `created_at`) → colonnes Entreprise/Inscription toujours vides.
- [ ] T149 [P2] [US2] `front/admin-dashboard/src/views/users/UserDetailView.vue:16,20-21` : `isLoading`/`errorMessage` jamais déclarés → spinner/erreur morts, catch → console.error seulement.
- [ ] T150 [P2] [US2] `front/admin-dashboard/src/views/settings/HolidaysView.vue:74,96,103,122,144,197` : `$t(key, {vars})` sans interpolation (signature main.js `(key, fallback)`) → placeholders bruts `{country}`, `{year}`…
- [ ] T151 [P3] [US2] `front/admin-dashboard/src/views/webhooks/WebhooksView.vue:198-219` : save/test/delete sans retour utilisateur (catch → console.warn) → toasts/états.
- [ ] T152 [P3] [US2] `front/admin-dashboard/src/views/subscriptions/SubscriptionsView.vue:199-200` : échec de chargement silencieux (catch → console.error, KPIs 0) → bandeau + retry.
- [ ] T153 [P3] [US2] `front/admin-dashboard/src/views/growth/GrowthDashboardView.vue:66,157-158` : statut « Approuvé » pour tout ≠ pending + échec silencieux présenté comme données vides.
- [ ] T154 [P3] [US2] `front/admin-dashboard/src/views/growth/GrowthDashboardView.vue:178` : `prompt()` natif restant (non i18n) → dialog stylé.
- [ ] T155 [P3] [US2] `front/admin-dashboard/src/composables/useNotificationStream.js` : orphelin (0 import) → supprimer ou brancher.

## Phase 3 — API backend (US3)

- [ ] T156 [P1] [US3] `api/app/Modules/Billing/Application/Actions/VerifyTrialSignup.php:43-48,93,131` : race double-provisioning trial (CompanyRequest non verrouillée) → `lockForUpdate` + transition atomique ; test concurrence.
- [ ] T157 [P1] [US3] `api/app/Modules/Payroll/Interfaces/Api/V1/BulkPaymentController.php:61-75` + `api/app/Jobs/ProcessBulkPaymentJob.php:104-116` : TOCTOU Redis + slips sans verrou → garde atomique (transition statut) ; test double dispatch.
- [ ] T158 [P2] [US3] `api/app/Core/Auth/Interfaces/Api/V1/AuthController.php:210-221` : OAuth Google auto-crée employé tenantless + token → refus explicite (aligné #2636).
- [ ] T159 [P2] [US3] `api/app/Modules/Growth/.../PartnerDashboardController.php:63-66,84-86` + `PartnerService.php:151-171` : races partners/payouts + `$e->getMessage()` brut au client → contrainte unique, verrou, erreurs génériques + log.
- [ ] T160 [P2] [US3] `api/routes/modules/sso.php:12-17` (callbacks publics sans throttle) + `api/routes/modules/growth.php:22` (groupe sans throttle) → aligner `throttle:api`/`platform-sensitive`.
- [ ] T161 [P3] [US3] `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PlatformAdminDashboardController.php:89-122,140-260` + `MetricsController.php:31-45` + `PlatformHrReportController.php:72-77` : ~15 catches silencieux → `Log::error` + 5xx explicite.
- [ ] T162 [P3] [US3] `api/app/Jobs/ProvisionDemoTenantJob.php:42,63` : `issueDemoAccess()` appelé 2× → 1er magic link mort → une seule émission.

## Phase 4 — Mobile Flutter (US4)

- [ ] T163 [P1] [US4] `front/mobile_apps/leopardo_hr/lib/features/onboarding/screens/onboarding_screen.dart:18,178` : `_complete(int)` vs `completeStep(String)` → erreurs de typage (compile KO) → `String`.
- [ ] T164 [P1] [US4] `front/mobile_apps/leopardo_manager/lib/app.dart:215-221` : route `/cabinet/folder/:folderId` déclarée 2× (la 1ʳᵉ gagne, `int.parse` crash) → garder la version `int.tryParse` (T121).
- [ ] T165 [P2] [US4] `front/mobile_apps/leopardo_hr|manager/lib/features/modules/data/modules_repository.dart:272,281` : `PUT /notifications/{id}/read` et `/read-all` (backend = PATCH/POST) → 405 → corriger les méthodes.
- [ ] T166 [P2] [US4] `front/mobile_apps/leopardo_marketing/lib/main.dart:76-93` : aucune auth (401 systématique sur `/marketing/*`) → flux login ou gate explicite.
- [ ] T167 [P2] [US4] POST non-idempotents avec retries : `social_post_repository.dart:15,20`, `company_request_screen.dart:47`, `user_auth_repository.dart:141`, `ai_chat_repository.dart:10` → `maxRetriesOverride: 0` (classe #2742).
- [ ] T168 [P3] [US4] `leopardo_marketing/.../editorial_calendar_screen.dart:25` : `DateTime.parse` non gardé → `tryParse`.
- [ ] T169 [P3] [US4] Méthodes mortes : `attendance_repository.dart:222` (getMyQuickEstimate), `cabinet_repository.dart:53,71` (update/deleteFolder), `project_repository.dart:12,22` + provider, `push_notification_repository.dart`, `vehicle_position_repository.dart:12`, `organigramme_repository.dart` (getDepartmentHierarchy), `user_auth_repository.dart` (getCompanyRequests), `payroll_repository.dart` (getMyPaySlips) → retirer ou brancher.
- [ ] T170 [P3] [US4] `smart_attendance_repository.dart:43,54` (hr/manager) et `:45` (employee) : retries sur approve/reject/zone_enter → `maxRetriesOverride: 0`.
- [ ] T171 [P3] [US4] Route `/onboarding` orpheline (hr/manager) : aucune entrée UI ni référence `MobileExperienceService` → ajouter l'entrée (T117) ou retirer.

## Phase 5 — Cohérence transversale (US5)

- [ ] T172 [P1] [US5] Durée d'essai : `VerifyTrialSignup.php:274` (fallback 30) → 14 (PlanSeeder canonique) ; manifest PWA + docs « 30 jours » → 14 ; arbitrer PR #2972 (commentaire).

## Phase 6 — Fermeture d'issues stale (preuve code)

- [ ] T173 [P2] [US5] Fermer avec commentaire+preuve code : #2802-#2811 (corrigés #2851), #2794-#2795 (corrigés #2919), #2594 (corrigé #2935), + issues #2721-#2793 référencées par PRs mergées (#2938-#2942, #2901, #2932, #2934) après vérification code sur main (script `check-issues-left-open-by-merged-prs.sh` ; skip si branche `fix/<n>-*` existe).

## Dependencies

- US1 (T135-T147) : indépendante — bloc vitrine (front/web).
- US2 (T148-T155) : indépendante — bloc admin (front/admin-dashboard).
- US3 (T156-T162) : indépendante — bloc API (api/).
- US4 (T163-T171) : indépendante — bloc mobile (front/mobile_apps + api).
- US5 (T172) : touche api + web + docs.
- T173 : indépendante (GitHub API).

## Implementation strategy

- MVP P1 : T135, T136, T156, T157, T163, T164 (issues P1) — livrés en premier.
- Vague 2 : P2/P3 par domaine, en parallèle.
- Validation : build vitrine + lint admin + PHPStan strict + tests ciblés backend ; `flutter analyze` (mobile) ; CI comme porte finale.

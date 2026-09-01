# Tasks — QA Audit Expert v3 2026-08-15 (manquements nouveaux)

> Format strict : `- [ ] [TaskID] [P?] [Story?] Description avec chemin de fichier`.
> Chaque user story forme un incrément testable indépendamment.

## Phase 1 — Setup

- [ ] T001 Créer la branche `docs/qa-audit-expert-v3-2026-08-15` depuis `origin/main` avec les artefacts Spec Kit (spec.md, findings-registry.md, tasks.md, plan.md) et ouvrir la PR docs (Closes issue registre).

## Phase 2 — US1 : Console super-admin /chat /training /webhooks (P1)

- [ ] T002 [US1] Retirer `requiresTenant: true` des routes `/chat`, `/training`, `/webhooks` dans `front/admin-dashboard/src/router/index.js:217-253` (les vues consomment désormais des endpoints `/admin/*` super-admin).
- [ ] T003 [US1] TrainingView — onglet Catalogue : soit endpoint `/admin/training/courses` cross-tenant côté API, soit retrait de l'onglet avec état honnête (`front/admin-dashboard/src/views/training/TrainingView.vue:248`).
- [ ] T004 [US1] `npm run lint` + build admin verts ; PR `fix/<issue>-admin-console-guard` avec `Closes #<issue>` + CHANGELOG.

## Phase 3 — US2 : Policy approvals réellement invoquée (P2)

- [ ] T005 [US2] Ajouter `$this->authorize('approve'/'reject'/'viewAny', $approvalRequest)` dans `api/app/Modules/Attendance/Interfaces/Api/V1/ApprovalController.php` (approve l.121, reject l.161, pending l.106-118).
- [ ] T006 [US2] Vérifier `ApprovalRequestPolicy` (manager-only, tenant-scope) ; ajouter `api/tenant` scoping si absent.
- [ ] T007 [US2] Test de régression `api/tests/Feature/ApprovalAuthzTest.php` : employé → 403, manager → 200, cross-tenant → 404 ; phpstan diff + pint verts ; PR `fix/<issue>-approval-policy` + CHANGELOG.

## Phase 4 — US3 : FCM placeholder keys (P2)

- [ ] T008 [US3] Retirer/gater les fichiers `google-services.json` placeholder (employee/hr/manager) et `GoogleService-Info.plist` (employee/hr/manager/platform_admin) : `.gitignore` + documentation du mécanisme d'injection (CI secret ou dart-define).
- [ ] T009 [US3] `PushNotificationService` : échec de bootstrap visible (log/statut) au lieu de silencieux ; PR `fix/<issue>-fcm-placeholder-keys` + CHANGELOG.

## Phase 5 — US4 : flutter analyze vert (P2)

- [ ] T010 [US4] Casser le cycle de providers dans les 3 apps tenant (employee/manager/hr) : `apiClientProvider` ne doit plus référencer `authProvider` dans son initialiseur (`core_providers.dart:44` ; auth_provider.dart:198/194/188) — ex. provider dédié `onUnauthorizedHandlerProvider` ou `ref.read` différé via `Ref` passé à `ApiClient`.
- [ ] T011 [US4] `leopardo_platform_admin` : déplacer les imports avant les déclarations (`platform_admin_app.dart:19-24`) + `WidgetRef`→`Ref` (:104).
- [ ] T012 [US4] `leopardo_marketing` : réparer les 44 erreurs (editorial_calendar_screen vs API du design system, social_post_repository return type, main.dart TenantTheme.dark) — ou documenter le retrait de l'app (connexe #2661).
- [ ] T013 [US4] `leopardo_manager` : `DateTime?`→`DateTime` `attendance_repository.dart:552`.
- [ ] T014 [US4] `flutter analyze` 0 erreur × 6 apps ; PR `fix/<issue>-mobile-analyze-green` + CHANGELOG.

## Phase 6 — US5 : SSRF + RBAC rh.php (P3)

- [ ] T015 [US5] `CameraService::testRtsp` : refuser loopback/privés/link-local (`NotPrivateUrl` existant, cf. `WebhookController.php:157`) + port 554 par défaut ; 422 explicite ; tests.
- [ ] T016 [US5] `routes/modules/rh.php:33` : grouper les routes d'écriture sensibles sous `api.manager` (employees, attendance corrections/logs, payrolls, departments/positions/sites/schedules, announcements, evaluations) sans casser les flux employee (vérifier chaque route).
- [ ] T017 [US5] PR `fix/<issue>-rtsp-ssrf` + PR `fix/<issue>-rh-rbac` + CHANGELOG + RBAC_ROUTE_MATRIX à jour.

## Phase 7 — US6 : SEO canonical + orphelins (P3)

- [ ] T018 [US6] Ajouter `canonical: \`${SITE_URL}${path}\`` dans les 8 layouts landing (employes, documents, comptabilite, marketing, demo, guides/*) — modèle `pricing/layout.tsx:11`.
- [ ] T019 [US6] Supprimer `src/lib/caching-config.ts`, `src/components/OptimizedImage.tsx`, `src/hooks/useIntersectionObserver.ts` après vérification rg ; lint + build vitrine verts.
- [ ] T020 [US6] PR `fix/<issue>-vitrine-seo` + CHANGELOG.

## Phase 8 — US7 : N+1 + logique paie (P3)

- [ ] T021 [US7] `PaymentBatchController::markPaid` : `load('items.employee')` au lieu du find par item.
- [ ] T022 [US7] `FleetController::liveMap` : appel Traccar agrégé (`/api/positions?deviceIds=…`).
- [ ] T023 [US7] `SocialDeclarationController` : extraire l'agrégation commune dans `SocialDeclarationService` (ou issue de dette dédiée avec scope) ; PR + CHANGELOG.

## Phase 9 — Hygiène résiduelle (P3)

- [ ] T024 Retirer les modales/refs mortes de `TaxRatesView.vue` (historyOpen/historyItems) + supprimer `useFocusTrap.js`/`useAnnouncer.js` après vérification rg.
- [ ] T025 Routes GoRouter mortes app RH (`/approvals`, `/manager/anomalies`, `/manager/corrections`) : brancher ou retirer.
- [ ] T026 Drift baseline PHPStan strict : régénérer `phpstan-strict-baseline.neon` (ou issue de dette dédiée) pour que la CI strict redevienne significative.
- [ ] T027 PR finale hygiène + CHANGELOG.

# Tasks — QA 360° Audit Expert 20 (2026-08-16)

> Format strict : `- [ ] [TaskID] [P?] [Story?] Description avec chemin de fichier`.
> Chaque tâche → une issue GitHub `[QA][Px][surface]...` (protocole Spec Kit).
> P0 déjà en PR : #4295 (errors.php), #4308 (EmployeeService).

## API / Backend

- [ ] T001 [P2] [US2] `PayrollRunController.php:232,300,344,393` : `localized_message => $e->getMessage()` → `__('errors.'.$e->errorCode())` ; ajouter `PAYROLL_ALREADY_VALIDATED`/`PAYROLL_RUN_LOCKED` (+ variantes) dans `api/lang/{fr,en,ar,tr}/errors.php` ; garder le code stable ; test que le body ne contient jamais le message brut.
- [ ] T002 [P2] [US2] `AttendanceController.php:325,328,416,478,516,522` : 6 messages FR → catalogue `attendance.*`/`errors.*` (clés à ajouter ×4 locales) ; déplacer la validation business timezone-aware dans `AttendanceService` (cohérence #3245).
- [ ] T003 [P2] [US2] FR résiduels : `PartnerDashboardController:102-103,113-114` (localized_message FR), `AttendanceModeController:75,100` (message FR sans localized_message), `PaymentBatchController:68,183`, `PaySlipController:323`, `CompanyRequestController:61` → `__('errors.KEY')` + codes stables.
- [ ] T004 [P3] [US2] `PlatformAuthController:45` (message FR à côté d'un code), `SSOController:117,125,158,166` (FR), `RequireTenantCountry:34-35`, `bootstrap/app.php:157` (PostTooLarge FR) → catalogues ×4.
- [ ] T005 [P3] [US2] `ContractController:232,250,272` : `$e->getMessage()` → code stable + localized_message ; clés `errors.CONTRACT_*` ×4 locales (pattern #3810).
- [ ] T006 [P3] [US2] Supprimer la définition dupliquée `PATCH /notifications/{id}/read` de `routes/modules/dashboard.php:40` (canonique : `rh.php:177`) ; garder FrontendApiContractTest vert.
- [ ] T007 [P3] [US2] `routes/modules/sso.php:12` : ajouter `->middleware('throttle:api')` sur `GET /sso/providers` (parité avec le reste du fichier).
- [ ] T008 [P3] [US2] `DetectSilentEdgeNodes.php` : supprimer la commande (colonnes inexistantes, non planifiée) ou la porter sur le modèle `EdgeNode` comme `edge:monitor` ; mettre à jour `bootstrap/app.php` si suppression.
- [ ] T009 [P3] [US2] Unifier les exceptions paie dupliquées (`App\Exceptions` vs `App\Modules\Payroll\Domain\Exceptions`) : une classe canonique par concept ; aligner throwers (`PayrollService:8-9`) et catchers (`PayrollRunController:14`, `PayrollClosingService:10-13`) ; supprimer `SalaryAdvanceAmountExceedsSalaryException` si inutilisée.
- [ ] T010 [P3] [US2] `config/edge.php:29`, `config/sentry.php:47`, `config/cors.php:32` : remplacer l'URL Render en dur par `env('APP_URL')`/vide (fallback) — ne pas coder l'hostname de prod dans les configs.
- [ ] T011 [P3] [US2] Ajouter des Feature tests pour `ApiTokenController` (scopes, RBAC 401/403, isolation tenant) et `GrowthAdminController` (payouts/rates admin).

## Vitrine Web

- [ ] T012 [P1] [US3] `FAQSection.tsx:119,128,138` : comparer avec `item.id ?? index` des deux côtés (openId et rendu/rotation) ; ajouter `aria-expanded={open}` + `aria-controls` ; test unitaire de rendu sur item sans id (module pages).
- [ ] T013 [P3] [US3] `data/docs-page.ts:88,190,292,394` : ajouter `id="intro"` à la section hero de `docs/page.tsx` (ou retarget le lien).
- [ ] T014 [P3] [US3] a11y : `NewsletterForm.tsx:48-53` (label/aria-label email), `docs/page.tsx:152-157` (search), `SignupForm.tsx:517-530` (6 OTP aria-label).
- [ ] T015 [P3] [US3] `(dashboard)/(marketing)/social/page.tsx:322,360` : `aria-label="Fermer"` → copie locale du dashboard.
- [ ] T016 [P3] [US3] Supprimer `common/Select.tsx` (mort) ; `Textarea.tsx:28` : `Math.random()` → `useId()` (comme `Input.tsx:24`) ; vérifier l'hydratation SSR.
- [ ] T017 [P3] [US3] `contact/page.tsx:149-150` : adresse/horaires dans le copy par locale (Record<AppLocale, …>).
- [ ] T018 [P2] [US3] `/case-studies` (liste + `[slug]`) : data par locale (pattern `data/testimonials.ts`) ; passer `locale` à `getCaseStudy` ; `case-studies.ts:27-31` moduleMeta localisés.

## Admin Dashboard

- [ ] T019 [P1] [US4] `SystemView.vue:237` : `GET /admin/metrics/overview` → `/platform/metrics/overview` ; vérifier l'auth super_admin et le FrontendApiContractTest ; supprimer le double toast (interceptor + local).
- [ ] T020 [P2] [US4] Lots i18n #4206 suivants : traduire FleetView, SystemView, SettingsView, GlobeView+MiniGlobe, GrowthDashboardView + titres toasts `realtime.js:234-293` (catalogues fr/en/ar/tr).
- [ ] T021 [P3] [US4] FR partiels : `WebhooksView:162`, `TrainingView:263`, `AnalyticsView` (~20 libellés), `SystemAlertsOverlay:31`.
- [ ] T022 [P3] [US4] Ajouter aux 4 catalogues les 10 clés `t()` manquantes (exports.historyClientOnly, exports.clientSpaceNote, exports.historyError, users.impersonation.copyFailed, users.confirm.title, tax_slabs.reset_confirm_title, tax_slabs.delete_confirm_title, social_contrib.delete_confirm_title, holidays.islamic.confirm_title, holidays.islamic.delete_confirm_title).
- [ ] T023 [P3] [US4] Supprimer `components/common/MetricCard.vue` (mort — doublon de `components/analytics/MetricCard.vue`).
- [ ] T024 [P3] [US4] États d'erreur : `ChatView:148,159` (retry + état visible), `WebhooksView:171-176` (ne pas vider le dropdown au catch), `SystemView:230-231` (message d'échec dans la carte + éviter le double toast).
- [ ] T025 [P3] [US4] `FleetView.vue:159` : échapper `plate_number`/`brand`/`model`/`assigned_to` avant `bindPopup` (ou construire le popup en nœuds texte).

## Mobile (Flutter)

- [ ] T026 [P2] [US5] Smart-Attendance (employee/manager/hr, 18 fichiers) : ~26+ chaînes FR → `AppLocalizations` (ARB core, clés existantes ou à ajouter ×4 locales) ; écrans : smart_attendance_screen, smart_attendance_dashboard_screen, pending_sessions_screen (manager+hr), config/preferences.
- [ ] T027 [P3] [US5] `leopardo_marketing/lib/main.dart:41` : initialiser les 4 locales (`fr`, `ar`, `tr`, `en`) comme les autres apps.
- [ ] T028 [P3] [US5] `leopardo_employee/.../smart_attendance_screen.dart:586` : `DateFormat('dd/MM/yyyy', deviceIntlDateLocale)` → pattern locale-aware (skeleton intl) ; audit des 7 `HH:mm` sans locale.
- [ ] T029 [P3] [US5] `leopardo_core/lib/offline/services/sync_service.dart:61` : stocker la `StreamSubscription` et la cancel dans `stop()` (miroir `offline_sync_service.dart`).
- [ ] T030 [P3] [US5] `attendance_provider.dart` (employee:118, manager:107, hr:107) : catch résumé → `debugPrint` + flag `load_degraded` (parité avec le chemin principal).

## CI / Ops / Docs

- [ ] T031 [P1] [US6] `render.yaml:161` : `--queue=webhooks,audit,notifications,pdf,payroll,documents,default` (conforme AGENTS.md:346) ; vérifier `queue:health-check` couvre toutes les queues.
- [ ] T032 [P2] [US6] `deploy-staging.yml:20-21,46-71` : remplacer le gate `workflow_run.conclusion` par le polling SHA borné (pattern deploy-main #3545) ; skip explicite `::warning::` + step summary.
- [ ] T033 [P2] [US6] `coverage-gate.yml:20,49-57` : consommer l'artifact clover de tests.yml (workflow_run) ou gating `api/**` sur le push main ; ne pas re-run la suite complète sur docs/mobile.
- [ ] T034 [P2] [US6] `mobile-distribute(-main).yml` : entrée `hr` → `secrets.FIREBASE_APP_ID_HR` explicite (pas de fallback générique ambigu) ; aligner la doc.
- [ ] T035 [P3] [US6] CHANGELOG [Unreleased] : dé-dupliquer les entrées exactes (garder la plus récente) ; ajouter un garde anti-doublon près des checks governance existants.
- [ ] T036 [P3] [US6] `docs/CI_CD_SECRETS.md` : régénérer l'inventaire depuis `.github/workflows/*.yml` (retirer FIREBASE_APP_ID_HR si non utilisé, ajouter CLOUDFLARE_*, GOOGLE_SERVICES_JSON, VITE_WEBSOCKET_URL, K6_*).
- [ ] T037 [P3] [US6] `i18n-enterprise.yml:6-20` : réduire les chemins push main aux dossiers de premier niveau (retirer les sous-ensembles redondants).

## Clôture

- [ ] T038 [P] [US1-US6] Vérifs finales : lint/tsc/jest/build vitrine + admin verts, actionlint, php -l complet, EmployeesRbacTest + suites touchées vertes, CHANGELOG ; merger les PRs vertes et supprimer les branches.

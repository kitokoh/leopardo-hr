# Registre des manquements — QA 360° Audit Expert 20 (2026-08-16)

> Session d'audit global du repo kitokoh/leopardo-hr (main @ ee458778, puis d7bbf1dd).
> Périmètre : API/backend, vitrine web, admin-dashboard, mobile (6 apps), CI/workflows, edge, kiosk.
> Méthode : 5 audits statiques par sous-agents (api/web/admin/mobile/ci) + vérification manuelle
> de chaque finding (lecture du code + preuve file:line) + exécution locale de tests ciblés
> (PostgreSQL 16 local) pour les findings P0/P1 backend.
> Dé-duplication stricte contre les ~30 issues ouvertes et les PRs mergées du jour.
> Chaque ligne `NOUVEAU` → spec-kit spec.md + tasks.md + issue GitHub.

## 0. Découvertes P0 — main cassé (traitées en priorité, hors audit)

| ID | Sév | Constat | Preuve | Statut |
|----|-----|---------|--------|--------|
| P0-1 | P0 | Merge #4275 (442d5138) : `api/lang/{fr,en,ar,tr}/errors.php` sans fermeture `];` → ParseError PHP → 500 sur toute réponse `__('errors.*')` | `php -l` échoue sur les 4 fichiers | Issue #4291 → PR #4295 (CI) |
| P0-2 | P0 | `EmployeeService::create/update` passe `role/manager_role/status/company_id` (non fillable #3677) dans `create()`/`fill()` → silencieusement perdus → `EmployeeResource:79 getAppDownloadLink(null)` TypeError → **500 sur POST /api/v1/employees**, company_id absent = hors tenant, rôles jamais persistés. 4 tests `EmployeesRbacTest` rouges sur main (vérifié localement) | `api/app/Modules/HR/Infrastructure/Services/EmployeeService.php:58,133` ; `Employee.php:139-195` ; `EmployeeResource.php:79` | Issue #4307 → PR #4308 (CI) |

## A. Findings NOUVEAUX (cette vague → issues)

### API / Backend

| ID | Sév | Constat | Preuve | Task |
|----|-----|---------|--------|------|
| A-01 | P2 | `PayrollRunController` : `localized_message => $e->getMessage()` (×4 sites) — message d'exception brut FR (ex. « Cette fiche de paie est déjà validée… ») renvoyé aux clients, contourne le garde #3810/#4171 du renderer ; codes `PAYROLL_ALREADY_VALIDATED`/`PAYROLL_RUN_LOCKED` absents de `lang/*/errors.php` | `PayrollRunController.php:232,300,344,393` ; `PayrollAlreadyValidatedException.php:13` | T001 |
| A-02 | P2 | `AttendanceController` : 6 messages FR en dur via `ValidationException::withMessages` (check-in/out futurs, correction déjà traitée, ordre sortie/entrée) | `AttendanceController.php:325,328,416,478,516,522` | T002 |
| A-03 | P2 | FR résiduels : `PartnerDashboardController:102-103,113-114` (`localized_message` FR littéral), `AttendanceModeController:75,100` (`message` FR sans localized), `PaymentBatchController:68,183`, `PaySlipController:323` (abort 404 FR), `CompanyRequestController:61` | cf. preuves | T003 |
| A-04 | P3 | FR auth/SSO/middleware : `PlatformAuthController:45` (message FR à côté du code), `SSOController:117,125,158,166`, `RequireTenantCountry:34-35`, `bootstrap/app.php:157` (PostTooLarge) | cf. preuves | T004 |
| A-05 | P3 | `ContractController` : `message => $e->getMessage()` (×3, 422) — fuite de phrasing interne EN (« Only draft contracts can be activated. ») sans code ni localized_message | `ContractController.php:232,250,272` ; `ContractLifecycleAction.php:26,37,48` | T005 |
| A-06 | P3 | Route dupliquée `PATCH /notifications/{id}/read` : `rh.php:177` (`{notification}` + whereNumber) vs `dashboard.php:40` (`{id}`, chargée après → gagne) — paramètres/contraintes contradictoires | `routes/modules/rh.php:177`, `routes/modules/dashboard.php:40` | T006 |
| A-07 | P3 | `GET /sso/providers` seul endpoint public sans throttle (le fichier revendique « throttles sur les endpoints publics ») | `routes/modules/sso.php:12` | T007 |
| A-08 | P3 | Commande `edge:detect-silent-nodes` : SQL brut sur colonnes inexistantes du schéma UUID actuel ; non planifiée (bootstrap le note « would fail every run ») | `app/Console/Commands/DetectSilentEdgeNodes.php:47-69,99-132` | T008 |
| A-09 | P3 | Exceptions paie dupliquées (`App\Exceptions` vs `App\Modules\Payroll\Domain\Exceptions`) — `PayrollService` importe les unes, le controller catch les autres → catchs disjoints silencieux ; `SalaryAdvanceAmountExceedsSalaryException` inutilisée | `PayrollService.php:8-9`, `PayrollRunController.php:14`, `PayrollClosingService.php:10-13` | T009 |
| A-10 | P3 | URLs prod Render hardcodées dans les configs : `config/edge.php:29`, `config/sentry.php:47`, `config/cors.php:32` → `https://gestionemployerbackend.onrender.com` | cf. preuves | T010 |
| A-11 | P3 | Contrôleurs sensibles sans test : `ApiTokenController` (tokens API scoped) et `GrowthAdminController` (payouts/rates) — zéro Feature test (RBAC 401/403 + isolation tenant) | `routes/modules/dashboard.php:40-42`, `routes/modules/growth.php:36-41` | T011 |

### Vitrine Web

| ID | Sév | Constat | Preuve | Task |
|----|-----|---------|--------|------|
| W-01 | P1 | `FAQSection` : accordéon inutilisable sur les pages modules (items sans `id`) — `openId` reçoit `item.id ?? index` mais les tests rendu/rotation comparent `openId === item.id` (undefined) → réponses jamais ouvertes, chevrons figés. Cassé sur /employes, /documents, /comptabilite, /marketing. Pas de `aria-expanded`/`aria-controls` non plus | `front/web/src/modules/vitrine/components/sections/FAQSection.tsx:119,128,138` ; `content.ts:144-168` ; `(landing)/page.tsx:176-180` (le seul endroit qui injecte des `id`) | T012 |
| W-02 | P3 | Ancre morte `/docs#intro` : 4 locales pointent `href:'/docs#intro'` mais `docs/page.tsx` n'a pas d'`id="intro"` | `data/docs-page.ts:88,190,292,394` | T013 |
| W-03 | P3 | a11y : inputs sans nom accessible — `NewsletterForm.tsx:48-53` (email placeholder-only, présent dans tous les footers), `docs/page.tsx:152-157` (search), `SignupForm.tsx:517-530` (6 OTP sans aria-label) | cf. preuves | T014 |
| W-04 | P3 | `aria-label="Fermer"` FR en dur dans le dashboard (page social, 2 boutons) alors que le dashboard est FR/EN/TR/AR | `front/web/src/app/(dashboard)/(marketing)/social/page.tsx:322,360` | T015 |
| W-05 | P3 | `common/Select.tsx` exporté mais importé nulle part (mort) ; `Textarea.tsx:28` utilise `Math.random()` pour l'id → mismatch SSR/hydratation (le pattern `useId()` existe déjà dans `Input.tsx:24`) | `components/common/Select.tsx:25,33`, `Textarea.tsx:28` | T016 |
| W-06 | P3 | `/contact` : valeurs hardcodées FR (« Alger, Algérie », « Lun-Ven 9h-18h (GMT+1) ») rendues dans les 4 locales (labels localisés, valeurs non) | `front/web/src/app/(landing)/contact/page.tsx:149-150` | T017 |
| W-07 | P2 | `/case-studies` liste + `/[slug]` : data 100 % FR dans toutes les locales (résiduel #3248 non traité — les 4 autres pages FR restantes sont déjà suivies par #4196/#4185/#4218) | `case-studies/page.tsx:10-55`, `[slug]/page.tsx:75-157`, `lib/case-studies.ts:27-31` | T018 |

### Admin Dashboard

| ID | Sév | Constat | Preuve | Task |
|----|-----|---------|--------|------|
| AD-01 | P1 | `SystemView` appelle `GET /admin/metrics/overview` — route inexistante (le backend expose `/platform/metrics/overview`) → 404 permanent, carte Infrastructure « Non disponible » + double toast à chaque visite `/system` | `SystemView.vue:237` vs `api/routes/api.php:252` | T019 |
| AD-02 | P2 | 5 vues 100 % FR en dur (post-lots #4206) : FleetView, SystemView, SettingsView, GlobeView+MiniGlobe, GrowthDashboardView + titres toasts FR dans `realtime.js:234-293` | cf. preuves | T020 |
| AD-03 | P3 | FR partiels : WebhooksView:162, TrainingView:263, AnalyticsView (~20 libellés), SystemAlertsOverlay:31 | cf. preuves | T021 |
| AD-04 | P3 | 10 clés `t()` référencées absentes des 4 catalogues → fallback FR inline toujours (ex. `exports.historyClientOnly`, `users.impersonation.copyFailed`, `tax_slabs.reset_confirm_title`, `holidays.islamic.confirm_title`…) | cf. preuves | T022 |
| AD-05 | P3 | `components/common/MetricCard.vue` mort (doublon de `components/analytics/MetricCard.vue`, seule version importée) | grep imports | T023 |
| AD-06 | P3 | Catchs silencieux : `ChatView:148,159` (console.warn only → liste vide indistingable d'un vide réel), `WebhooksView:171-176` (échec → dropdown entreprise vide), `SystemView:230-231` (probe health muette + double toast) | cf. preuves | T024 |
| AD-07 | P3 | Sécurité XSS : `FleetView:159` — `bindPopup` Leaflet avec `plate_number`/`brand`/`assigned_to` tenants non échappés (injection HTML possible par un manager tenant) | `FleetView.vue:159` | T025 |

### Mobile (Flutter)

| ID | Sév | Constat | Preuve | Task |
|----|-----|---------|--------|------|
| M-01 | P2 | Feature Smart-Attendance livrée 100 % FR en dur (0 `AppLocalizations` dans les 18 fichiers Dart ; ~26+ chaînes : « Pointage Intelligent », « Sessions récentes », « La zone GPS… pas encore configurée »…) — nouveau module, hors périmètre #4194 | `leopardo_manager/lib/features/smart_attendance/**` (18 fichiers), `leopardo_employee/.../smart_attendance_screen.dart:54,144,336,396,487,512-513,570-576,776`, `leopardo_hr/.../pending_sessions_screen.dart:41,81,102,125` | T026 |
| M-02 | P3 | `leopardo_marketing/main.dart:41` n'initialise que `fr_FR` (les 5 autres apps initialisent les 4 locales) → `LocaleDataException` latente sur DateFormat non-FR | `leopardo_marketing/lib/main.dart:41` | T027 |
| M-03 | P3 | Résidu #4197 : `DateFormat('dd/MM/yyyy', deviceIntlDateLocale)` encore en dur dans le screen smart-attendance employee | `leopardo_employee/.../smart_attendance_screen.dart:586` | T028 |
| M-04 | P3 | `SyncService.start()` : `Connectivity().onConnectivityChanged.listen(...)` jamais annulé (leak de StreamSubscription par cycle start/stop) — `stop()` ne cancel que `_syncTimer` | `leopardo_core/lib/offline/services/sync_service.dart:61` (cf. `offline_sync_service.dart:18,106-107`) | T029 |
| M-05 | P3 | Catch muet chargement résumé journalier (employee/manager/hr) : `// Ignore summary loading errors` sans log ni état dégradé (le chemin principal pose `load_degraded`) | `attendance_provider.dart:118` (employee), `:107` (manager), `:107` (hr) | T030 |

### CI / Ops / Docs

| ID | Sév | Constat | Preuve | Task |
|----|-----|---------|--------|------|
| C-01 | P1 | Worker Render (`render.yaml:161`) n'écoute pas `webhooks` ni `audit` → `DispatchWebhook`/`WebhookListener` (queue `webhooks`) et `AuditLogger` (queue `audit`) ne sont **jamais consommés en prod** : webhooks partenaires et traces d'audit affamés. Contredit la règle AGENTS.md:346 (canonical = documents,pdf,payroll,notifications,webhooks,default) | `render.yaml:161` vs `Jobs/DispatchWebhook.php:38`, `Listeners/WebhookListener.php:21`, `Listeners/AuditLogger.php:22` | T031 |
| C-02 | P2 | `deploy-staging.yml:20-21,46-71` : garde `workflow_run.conclusion` seule (pattern famine #3545 toujours présent) — run annulé = skip silencieux du déploiement ; deploy-main a été corrigé, pas deploy-staging | cf. preuves | T032 |
| C-03 | P2 | `coverage-gate.yml` relance la suite pcov complète (62 min) déjà exécutée par `tests.yml` (Backend Coverage) et sur CHAQUE push main sans filtre paths → double coût sur pushes api, coût complet sur pushes docs/mobile | `coverage-gate.yml:20,49-57` vs `tests.yml:420-425` | T033 |
| C-04 | P2 | `mobile-distribute(-main).yml` : l'entrée `hr` retombe sur le fallback générique `FIREBASE_APP_ID` ; `FIREBASE_APP_ID_HR` documenté (`docs/CI_CD_SECRETS.md:26`) n'est jamais référencé → upload HR potentiellement vers la mauvaise app Firebase | `mobile-distribute-main.yml:74-78,106,180,195`, `mobile-distribute.yml:73-77,147,170,297,313` | T034 |
| C-05 | P3 | CHANGELOG [Unreleased] : doublons exacts (ex. « mojibake double-encodé » ×4, « gate /api/v1/demo-users » ×4, 2 entrées identiques :10 et :57) — 916 bullets, dizaines de doublons | `CHANGELOG.md:10,57,294,305,314,676,996,1018,1019,1031` | T035 |
| C-06 | P3 | `docs/CI_CD_SECRETS.md` : inventaire en dérive — liste `FIREBASE_APP_ID_HR` (jamais utilisé) et omet 8 secrets réellement utilisés (CLOUDFLARE_API_TOKEN, CLOUDFLARE_ACCOUNT_ID, GOOGLE_SERVICES_JSON, VITE_WEBSOCKET_URL, K6_*) | vs `.github/workflows/*.yml` | T036 |
| C-07 | P3 | `i18n-enterprise.yml` : chemins push main redondants (`front/web/src/**` + sous-ensembles `app/**`/`modules/**`, `front/admin-dashboard/src/i18n/**` + `src/**`) → 4+ workflows par push | `.github/workflows/i18n-enterprise.yml:6-20` | T037 |

## B. Constats vérifiés SAINS (pas d'issue, trace)

| Constat | Détail |
|---------|--------|
| Drift routes→controllers | Zéro : les 155 méthodes routées existent |
| Isolation tenant API | `BelongsToCompany` + policies : cohérent (payslip, expense, cabinet, vehicle, payroll-cycle, estimation vérifiés) |
| Endpoints publics | kiosk/ZKTeco/edge/marketing-leads : token/secret-gated, fail-closed |
| openapi.yaml | Structurellement sain (516 paths, $ref résolus) |
| SW vitrine | `sw.js` respecte #2983 (routes authentifiées exclues du cache) |
| SEO vitrine | Toutes les pages ont generateMetadata localisé + OG + hreflang ; pas de doublon seo-metadata.ts |
| Mobile endpoint drift | Zéro : 98 littéraux Dart vs 716 routes PHP, tous présents (mocks exclus) |
| Routes GoRouter dupliquées | Zéro (résolu sur main) |
| platform_admin | N'appelle que `/platform/*` |
| Edge/kiosk | install.sh télécharge + hash-check Caddyfile.edge ; kiosk.db 0600 ; body borné 64K ; rate-limit local ; pas d'IDs dupliqués |
| Realtime admin | PATCH `/notifications/{id}/read` + POST `/notifications/read-all` corrects |
| #4216 Workers Builds | Check externe non bloquant (5 contexts requis seulement), neutralisable côté dashboard Cloudflare |

# Registre des manquements — QA Audit Expert v3 2026-08-15

> Session de test experte du repo kitokoh/leopardo-hr (main @ d30b52da, 2026-08-15).
> Mission : tester la vitrine, le web, l'admin, les mobiles, les workflows, les APIs, les
> logiques, l'onboarding et la cohérence — tout manquement → spec + tasks + issues
> (méthode Spec Kit) puis implémentation, puis merge des branches.
> Méthode : 4 audits statiques par sous-agents (web/admin/mobile/API) + validation runtime
> locale (builds vitrine/admin, analyse Flutter 3.47 des 6 apps, migrations PostgreSQL +
> suite de tests backend, PHPStan strict level 8, Pint).

## ⚠️ Dé-duplication (protocole #2400)

Une campagne QA « expert #2 » parallèle (issues #2972→#3065, PR #3116) couvre déjà ~43
constats. Chaque ligne ci-dessous est marquée `NOUVEAU` ou `DÉJÀ COUVERT (#XXXX)` après
vérification exhaustive des issues ouvertes (101+ titres) et des branches/PRs en cours.
Seuls les `NOUVEAU` font l'objet d'issues dans cette vague.

## A. Validation runtime effectuée (preuves)

- [x] **Build vitrine Next.js** (`front/web`) : `npm run lint` → 0 erreur ; `npm run build` → OK
  (Compiled successfully, ~70 routes statiques/dynamiques). Avertissement `middleware` déprécié
  (convention `proxy`) — non bloquant.
- [x] **Build admin Vue** (`front/admin-dashboard`) : **ROUGE sur main** — `DocumentReportIcon`
  n'existe pas dans `@heroicons/vue/24/outline` → `vite build` échoue (confirmé localement,
  issue #3114 [P1], **corrigée dans cette vague** — PR #3123 : retrait des 4 entrées tenant de
  CommandPalette + 10 imports d'icônes morts ; lint 0 warning, build vert).
- [x] **Flutter analyze 3.47.0 stable (6 apps)** :
  - `leopardo_core` → **No issues found** ✓
  - `leopardo_employee` → **3 erreurs** (top_level_cycle apiClientProvider↔authProvider↔authRepositoryProvider)
  - `leopardo_manager` → **4 erreurs** (3× top_level_cycle + `DateTime?`→`DateTime` attendance_repository.dart:552)
  - `leopardo_hr` → **5 erreurs** (3× top_level_cycle + 2 typos onboarding_screen.dart — DÉJÀ COUVERT #3003)
  - `leopardo_platform_admin` → **8 erreurs** (5× `directive_after_declaration` platform_admin_app.dart:19-24, `WidgetRef`→`Ref` :104, unused import)
  - `leopardo_marketing` → **44 issues** (editorial_calendar_screen compile KO : AppColors.primary, AppTypography.headlineMedium, MobileSurface…, social_post_repository return type, main.dart TenantTheme.dark)
- [x] **PHPStan strict level 8** (`phpstan-strict.neon`) : **165 erreurs sur main** hors baseline
  (68 property.notFound, 28 argument.type, 14 cast.string, 14 argument.templateType…) —
  le baseline strict est stale ; la CI ne bloque que le diff.
- [x] **Migrations PostgreSQL** : public + tenant OK sur `leopardo_test` (PostgreSQL 14 local).
- [x] **Suite de tests backend** : exécution complète en cours (400 fichiers de test).
- [x] **Endpoints front↔API** : 145/145 appels admin résolus ; 110/110 appels mobile résolus
  (hors notifications read-all, cf. F-MO-01/F-WE-04 — DÉJÀ COUVERT #3047).

## B. Findings NOUVEAUX de cette vague

| ID | Sév | Surface | Constat | Preuve | Issue |
|----|-----|---------|---------|--------|-------|
| F-V3-01 | P2 | web | Canonical = homepage sur 8 pages landing (`/employes /documents /comptabilite /marketing /demo /guides/*`) : `seo.ts:30` replie sur `siteUrl` quand `canonical` est omis, et ces layouts ne passent pas de canonical → SEO auto-cannibalisé | `front/web/src/modules/vitrine/lib/seo.ts:30,77` ; `(landing)/employes/layout.tsx:8`, `documents/layout.tsx:7`, `comptabilite/layout.tsx:5`, `marketing/layout.tsx:5`, `demo/layout.tsx`, `guides/layout.tsx` | **NOUVEAU** |
| F-V3-02 | P3 | web | Orphelins : `src/lib/caching-config.ts`, `src/components/OptimizedImage.tsx`, `src/hooks/useIntersectionObserver.ts` (0 import ; le hook duplique la version vitrine utilisée) | rg 0 référence ; `src/modules/vitrine/hooks/useIntersectionObserver.ts` utilisé par AnimatedCounter | **NOUVEAU** (#2784 ne couvre pas ces fichiers) |
| F-V3-03 | P2 | admin | Guard `requiresTenant` bloque /chat, /training, /webhooks alors que leurs vues consomment des endpoints super-admin `/admin/*` (créés #2634) → console Webhooks/Formations/Chat IA inaccessibles | `router/index.js:217-224,237-253,394-398` vs `views/chat/ChatView.vue:129-168`, `views/training/TrainingView.vue:248-250`, `views/webhooks/WebhooksView.vue:158-216` | **NOUVEAU** |
| F-V3-04 | P3 | admin | TrainingView onglet « Catalogue » (défaut) toujours vide : `/v1/training/courses` est tenant-scope → 401 super-admin avalé silencieusement | `views/training/TrainingView.vue:248` vs `routes/modules/hr_extended.php:61` | **NOUVEAU** |
| F-V3-05 | P3 | admin | TaxRatesView modal « historique » morte : `historyOpen` jamais mis à true, `historyItems` jamais peuplé | `views/settings/TaxRatesView.vue:185,242,248,372-373` | **NOUVEAU** |
| F-V3-06 | P3 | admin | Composables orphelins `useFocusTrap.js` + `useAnnouncer.js` (0 import ; `useNotificationStream.js` DÉJÀ COUVERT #2995) | `src/composables/useFocusTrap.js`, `useAnnouncer.js` | **NOUVEAU** |
| F-V3-07 | P2 | api | `ApprovalRequestPolicy` enregistrée (AuthServiceProvider:90) mais jamais invoquée : `ApprovalController::approve/reject` (l.121,161) n'appellent aucune `authorize()` → tout employé authentifié peut approuver/rejeter n'importe quelle demande en attente (bypass latent, la policy manager-only est code mort) | `app/Modules/Attendance/Interfaces/Api/V1/ApprovalController.php:121,161` ; `app/Policies/ApprovalRequestPolicy.php:31-45` ; `routes/modules/hr_extended.php:55-58` | **NOUVEAU** |
| F-V3-08 | P3 | api | SSRF : `POST /cameras/test-rtsp` lance `ffprobe` (shell_exec) sur URL `rtsp://` fournie par l'utilisateur sans blocklist IP privées/loopback/link-local (port libre) | `app/Modules/Cameras/Infrastructure/Services/CameraService.php:380-392` ; `routes/modules/cameras.php:29` | **NOUVEAU** |
| F-V3-09 | P3 | api | N+1 : `PaymentBatchController::markPaid` lazy-load `employee` par item (~500 requêtes/batch) ; `FleetController::liveMap` 1 appel HTTP Traccar par véhicule | `PaymentBatchController.php:157-166`, `FleetController.php:47-59` | **NOUVEAU** (#2973 ne couvre que executeCalculateRun) |
| F-V3-10 | P3 | api | `SocialDeclarationController` (556 lignes) réimplémente 9× la même agrégation paie inline au lieu d'un service partagé (violation DDD, risque de divergence) | `SocialDeclarationController.php:28-77,112,211` ; ARCHITECTURE.md:162 | **NOUVEAU** |
| F-V3-11 | P3 | api | Défense en profondeur : routes d'écriture sensibles de `rh.php` (POST /employees, PUT /attendance/*, POST /payrolls, POST /departments|positions|sites|schedules|announcements|evaluations) sans `api.manager` au niveau groupe (contrairement à hr_extended/payroll_engine/dashboard) | `routes/modules/rh.php:33,47,90-92,130,139-161,192,217` | **NOUVEAU** (volet growth #3000 DÉJÀ COUVERT) |
| F-V3-12 | P3 | mobile | Routes GoRouter mortes app RH : `/approvals`, `/manager/anomalies`, `/manager/corrections` (aucune entrée UI ni référence backend) | `leopardo_hr/lib/app.dart:248,272,276` | **NOUVEAU** (#2801/#3011 ne couvrent pas ces 3 routes) |
| F-V3-13 | P2 | mobile | Clés FCM placeholder commitées dans les 6 plateformes : `google-services.json` (×3) `AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000` + `mobilesdk_app_id` zéro ; iOS `REDACTED_GOOGLE_API_KEY` → push notifications inopérantes en silence | `leopardo_employee|hr|manager/android/app/google-services.json:10,31,55,76,100,121,158` ; `*/ios/Runner/GoogleService-Info.plist:6` ; `leopardo_core/.../push_notification_service.dart:53-55,69-72` | **NOUVEAU** |
| F-V3-14 | P2 | mobile | **Flutter analyze rouge sur main** : cycle de providers `top_level_cycle` (apiClientProvider↔authProvider↔authRepositoryProvider) dans employee (3), manager (3), hr (3) — blocage CI mobile + builds | `leopardo_employee/lib/core/providers/core_providers.dart:44,127`, `leopardo_employee/lib/features/auth/providers/auth_provider.dart:198` (idem manager/hr) | **NOUVEAU** |
| F-V3-15 | P2 | mobile | `leopardo_platform_admin` compile KO : 5× `directive_after_declaration` (imports après déclarations, `platform_admin_app.dart:19-24`) + `WidgetRef`→`Ref` (:104) | `leopardo_platform_admin/lib/src/platform_admin_app.dart:19-24,104` | **NOUVEAU** |
| F-V3-16 | P2 | mobile | `leopardo_marketing` compile KO : 44 erreurs (editorial_calendar_screen : `AppColors.primary`/`AppTypography.headlineMedium`/`MobileSurface` inexistants, spread non-iterable ; `social_post_repository.dart:11` return type ; `main.dart:125` `TenantTheme.dark` inexistant) | `leopardo_marketing/lib/features/marketing/screens/editorial_calendar_screen.dart:34-99`, `leopardo_marketing/lib/features/marketing/repositories/social_post_repository.dart:11`, `leopardo_marketing/lib/main.dart:125` | **NOUVEAU** (le DateTime.parse #3008 est une des 44, mais le compile KO est nouveau) |
| F-V3-17 | P3 | mobile | `leopardo_manager` `attendance_repository.dart:552` : `DateTime?` passé à un paramètre `DateTime` → erreur de type | `leopardo_manager/lib/features/attendance/data/attendance_repository.dart:552:25` | **NOUVEAU** (voisin #3054 mais app/fichier différents) |
| F-V3-18 | P3 | api | Drift baseline PHPStan strict : 165 erreurs sur main non couvertes par `phpstan-strict-baseline.neon` (stale) — la CI diff-gate masque le drift ; tout PR touchant ces fichiers serait rouge | `/tmp/phpstan.log` (165 erreurs, 68 property.notFound…) | **NOUVEAU** |

## C. Findings DÉJÀ COUVERTS (vérifiés, trace — pas de nouvelle issue)

| Constat (vérifié) | Issue(s) existante(s) |
|-------------------|----------------------|
| Notifications read-all : PUT vs POST/PATCH (web + employee) | #3047 |
| Route `/cabinet/folder/:folderId` dupliquée (manager) | #3049, #3004 |
| og:image 404 ~20 pages + seo-metadata.ts mort | #3021, #3014 |
| Clé i18n `seo.pricing.description` absente | #3018, #2976 |
| UserDetailView isLoading/errorMessage morts | #2989 |
| GrowthDashboardView « Approuvé » pour tout ≠ pending | #2993 |
| useNotificationStream orphelin | #2995 |
| Bandeau maintenance jamais déclenchable | #3043 |
| CommandPalette entrées tenant + icônes | #3046, #3114 (corrigé dans cette vague) |
| Marketing sans authentification | #3006 |
| DateTime.parse non gardés (hr:552, geo_sessions…) | #3054 |
| `POST /employees/link-user` employee_id non validé | #3065 |
| Cockpit ~15 catches silencieux (dont PlatformHrReportController) | #3001 |
| Throttles growth / SSO callbacks | #3000 |
| HR onboarding_screen typos (int vs String) | #3003 |
| Marketing editorial_calendar DateTime.parse | #3008 |
| Pricing table vs cartes (connexe) | #3024 |
| Pricing card : employeeLimit vs priceNote (connexe #3024 — pas d'issue séparée, même fix) | #3024 |
| API build rouge (DocumentReportIcon) | #3114 — corrigé PR #3123 |

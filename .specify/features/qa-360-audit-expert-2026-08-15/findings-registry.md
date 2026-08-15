# Registre des manquements — QA 360° Audit Expert 2026-08-15

> Session d'audit global du repo kitokoh/leopardo-hr (main @ 1d512cc3).
> Périmètre : API/backend, vitrine web, admin-dashboard, mobile (4 apps), kiosk, edge, CI/workflows.
> Méthode : 4 audits statiques par sous-agents (api/web/admin/mobile+kiosk+edge+CI) avec
> vérification des fichiers cités, dé-duplication stricte contre les 51 issues ouvertes
> et les PRs récemment fusionnées (protocole anti-doublon #2400).
> Chaque ligne `NOUVEAU` → spec-kit spec.md + tasks.md + issue GitHub.

## A. Findings NOUVEAUX (cette vague → issues)

| ID | Sév | Surface | Constat | Preuve | Task |
|----|-----|---------|---------|--------|------|
| A-01 | P2 | API/security | Google OAuth callback auto-provisionne un compte `ordinary` actif + token Sanctum sans invitation ni feature gate — contourne la politique d'inscription sur invitation (#2617). Cohérence : `handleGoogleToken` 401 sur email inconnu. | `api/app/Core/Auth/Interfaces/Api/V1/AuthController.php:196-207` | T001 |
| A-02 | P3 | API | Messages d'exception bruts renvoyés dans les bodies d'erreur (~10 sites : import employés, bulk payments, webhooks, OAuth Google, tax slabs, contributions) — fuite SQL/Redis/internes aux tenants. | `EmployeeImportController.php:147`, `BulkPaymentController.php:122`, `WebhookController.php:226`, `TaxSlabController.php:164`, `SocialContributionController.php:157`… | T002 |
| A-03 | P3 | API | Import CSV employés : race check-then-create (exists puis create) → unique violation → 500 au lieu d'un 422 par ligne. Même classe que #3238 mais endpoint non couvert. | `EmployeeImportController.php:110-116,134,142-152` | T003 |
| A-04 | P3 | API/security | Scope global `BelongsToCompany` fail-OPEN : pas de compagnie courante → pas de scoping → requêtes cross-tenant silencieuses (ex. `WebhookController::index` sans filtre `company_id` explicite). | `api/app/Shared/Traits/BelongsToCompany.php:17-20`, `WebhookController.php:54-56` | T004 |
| W-01 | P2 | Web/e2e | 18/31 tests de navigation no-op silencieux : ils cliquent des liens navbar (employes/documents/comptabilite/marketing) qui n'existent plus, sous garde `isVisible()` → faux vert CI. | `front/web/e2e/navigation-and-links.spec.ts:16-49`, `conversion-funnel.spec.ts:157-160`, `dark-mode-toggle.spec.ts:184-187` | T005 |
| W-02 | P2 | Web/PWA | Service worker met en cache TOUTES les réponses GET ok, y compris /dashboard, /payroll, /employees authentifiés — contredit la décision #2983 (précache exclut ces routes car HTML privé). + `setInterval(update(), 60s)` sans cleanup. | `front/web/public/sw.js:63-73`, `src/components/PWAProvider.tsx:56-58` | T006 |
| W-03 | P2 | Web | /mobile gère sa propre langue via useState (défaut FR) indépendamment de `useVitrineLocale` → navbar locale ≠ body (UI mixte), état perdu à la navigation. | `front/web/src/app/(landing)/mobile/page.tsx:249-270` | T007 |
| W-04 | P2 | Web/SEO | Pages guides (×3) + /demo : pas de OpenGraph/Twitter ni image OG (les autres landing layouts utilisent `generateSEOMetadata`) → partage WhatsApp/réseaux = URL nue. | `front/web/src/app/(landing)/guides/layout.tsx`, `guides/{rh-startup,planning-employes,checklist-paie}/layout.tsx`, `demo/layout.tsx` | T008 |
| W-05 | P3 | Web/a11y | FAQ : input recherche sans label/aria-label, accordéons sans aria-expanded/aria-controls ; Navbar drawer `aria-label="Menu mobile"` FR en dur, boutons mobile sans aria-expanded. | `front/web/src/app/(landing)/faq/page.tsx:109,139`, `Navbar.tsx:379,406` | T009 |
| W-06 | P3 | Web | `seo-metadata.ts` (14 Ko) importé nulle part — doublon divergent de `seo.ts` (risque de drift des chaînes SEO/OG). | `front/web/src/modules/vitrine/lib/seo-metadata.ts` (0 importeur) | T010 |
| W-07 | P3 | Web | Footer : mapping href par position (sectionIndex-linkIndex) avec fallback silencieux `'#'` — toute évolution d'une locale produit des liens morts invisibles. | `front/web/src/modules/vitrine/components/Footer.tsx:54` | T011 |
| W-08 | P3 | Web | Navbar : changement de langue localStorage+html lang mais jamais `router.replace(?lang=)` → langue perdue au copy/share, divergence avec le schéma URL hreflang/sitemap (aggrave #3250). | `front/web/src/modules/vitrine/components/Navbar.tsx:333-346,389-396` | T012 |
| AD-01 | P2 | Admin | `realtime.js` appelle PUT `/v1/notifications/{id}/read` et `/read-all` alors que le backend expose PATCH/{id}/read et POST/read-all → 405 systématique, catch console.warn seul → notifications jamais marquées lues. | `front/admin-dashboard/src/stores/realtime.js:354,365` vs `api/routes/modules/rh.php:176-177` | T013 |
| AD-02 | P2 | Admin | CommandPalette liste encore 4 routes guardées `requiresTenant` (fleet OK via #3692, mais reports/audit/predictions + fleet déjà débloqués ?) → clic = toast warning + redirect dashboard. Header filtre déjà ces routes. | `front/admin-dashboard/src/components/common/CommandPalette.vue:122-128` | T014 |
| AD-03 | P3 | Admin | Titre d'onglet : clés i18n brutes (`marketing.oauth.nav_title`, `holidays.nav.title`) affichées littéralement dans `document.title` (guard router:403). | `front/admin-dashboard/src/router/index.js:317,335,403` | T015 |
| AD-04 | P2 | Admin | FleetView : échec `/v1/admin/fleet/alerts` avalé en silence (`catch → []`) sans état d'erreur ni retry — même famille que #3274 mais fichier hors PR #3696. | `front/admin-dashboard/src/views/fleet/FleetView.vue:180` | T016 |
| M-01 | P1 | CI/mobile | `mobile-distribute-main.yml` : l'entrée `hr` de la matrice (ajoutée #2661) tombe dans le fallback ternaire `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` (le `|| secrets.FIREBASE_APP_ID` de mobile-distribute.yml manque) → validation node exit 1 → distribution staging HR échoue à chaque push main. | `.github/workflows/mobile-distribute-main.yml:106,180,195` vs `mobile-distribute.yml:152,175,298,314` | T017 |
| M-02 | P2 | Edge | `edge/install.sh` ne télécharge jamais `Caddyfile.edge` alors que `edge/docker-compose.yml:97` monte `./Caddyfile.edge` → edge-proxy injoignable sur installs clients ; contexts de build `..`/`../front/web` résolus hors repo. | `edge/install.sh:56-80`, `edge/docker-compose.yml:16,52,97` | T018 |
| M-03 | P2 | CI | `branch-protection-guard.yml` (cron horaire) GET l'API branch protection avec `GITHUB_TOKEN` par défaut (contents:read) → 403 systématique car l'endpoint exige admin → garde aveugle. | `.github/workflows/branch-protection-guard.yml` | T019 |
| M-04 | P3 | CI/mobile | `mobile-distribute.yml` ET `mobile-distribute-main.yml` déclenchés sur push main avec chemins chevauchants → 4 APKs staging buildés/uploadés 2× par push. | `.github/workflows/mobile-distribute.yml`, `mobile-distribute-main.yml` | T020 |
| M-05 | P3 | Kiosk/security | Bridge desktop : `data/kiosk.db` (PII pointages) persisté avec umask par défaut (lisible monde) — PR #3698 a durci HTTP mais pas le fichier au repos. | `front/zkteco-kiosk/desktop-bridge/bridge.py` (création kiosk.db) | T021 |
| M-06 | P3 | Kiosk | Bridge : `_read_json` sans limite de taille de body, pas de rate-limit local sur `/local/punch` (le serveur distant throttle, pas le bridge). | `front/zkteco-kiosk/desktop-bridge/bridge.py:537-541` | T022 |
| M-07 | P2 | Mobile | `leopardo_manager/lib/app.dart` : 11 routes GoRoute dupliquées dans le même ShellRoute (artefact de #3223/#3205) — GoRouter utilise la 1ʳᵉ, doublons morts source de confusion. | `front/mobile_apps/leopardo_manager/lib/app.dart` (44 `path:` pour 33 routes uniques) | T023 |

## B. Findings DÉJÀ COUVERTS (vérifiés — pas de nouvelle issue, trace)

| Constat (vérifié) | Couverture |
|-------------------|------------|
| Fail-open marketing leads quand secret absent | #2688 (documenté, délibéré) |
| Curl-pipe install edge sans intégrité/checksum | #3529 |
| Hreflang alternates ?lang= sur routes non localisées | #3250 |
| FR codé en dur admin (Settings, DataTable, vues platform, modèles tenant) | #3270 (PR #3689 = navigation seule) |
| Tokens design legacy hors PR #3701 (ApprovalWidget, FleetView, AuditLogsView, ContractsView, TrainingView) | #3278 |
| Formatage locale gaps (UsersView fr-FR CSV, toLocaleString nus) | #3277 |
| FR en dur fichiers mobiles récents (access_denied_screen, bottom-nav shells) | #2740/#2755 |
| Route morte /users/:id admin | FIXÉ #3711 (merge) |
| Import CSV race — voir A-03 (déjà créé) | T003 |

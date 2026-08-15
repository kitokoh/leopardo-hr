# Registre des manquements — QA Audit Expert 2026-08-15

> Session de test experte du repo kitokoh/leopardo-hr (main @ 80c034f).
> Mission : tester la vitrine, le web, l'admin, les mobiles, les workflows, les APIs, les
> logiques, l'onboarding et la cohérence — tout manquement → spec + tasks + issues
> (méthode Spec Kit) puis implémentation.
> Méthode : 4 audits statiques par sous-agents (web/admin/mobile/cohérence API) + tests
> runtime (API Render live, vitrine Vercel live, admin Pages.dev live, suite de tests
> backend locale PostgreSQL, builds locaux).
>
> ⚠️ Dé-duplication : une campagne QA 2026-08-15 (issues #2646→#2813, ~90 tickets) couvre
> déjà la majorité des constats. Chaque ligne ci-dessous est marquée `NOUVEAU` ou
> `DÉJÀ COUVERT (#XXXX)` pour respecter le protocole anti-doublon (#2400). Seuls les
> `NOUVEAU` font l'objet de nouvelles issues dans cette vague.

## A. Vérifications runtime effectuées (preuves)

- [x] **API Render live** (gestionemployerbackend.onrender.com, health `4.23.5`, main = `4.24.0`) :
  - `POST /api/v1/auth/login` avec comptes démo (ahmed.benali@techcorp-algerie.dz,
    karim.aouad@techcorp-algerie.dz, sofiane.mrad@digitalflow.tn / password123) → **500 Server Error**
    (→ DÉJÀ COUVERT **#2652 [P0]**). Cause racine identifiée (voir F-01).
  - `admin@leopardo-rh.com/password123` (login tenant + platform) → 401 INVALID_CREDENTIALS ;
    `/api/v1/demo-users` → 404 (gate délibérée hors DEMO_MODE_ENABLED, testée en local)
    → DÉJÀ COUVERT **#2646** + **#2650** + PR #2773.
  - `/api-explorer` → **500** en prod ; rendu OK en local sur main (fix #2265 présent) →
    DÉJÀ COUVERT **#2627/#2632/#2812** (déploiement Render obsolète).
  - `/docs`, `/docs/openapi.yaml`, `/tester-guide`, `/api/v1/health`, `/health/live` → 200 OK.
  - Login email inconnu → 401 propre ; body vide → 422 ; `/auth/register` → 422. ✓
- [x] **Vitrine Vercel live** (gestionemployer-backend.vercel.app) :
  - `/blog` → **404** alors que le footer/hero exposent « Explore the blog » (DÉJÀ COUVERT #2276/#2609).
  - Liens `?lang=en|tr|ar` sur /pricing → 200 ; `/en|/tr|/ar` → 404 (locale par `?lang=`, pas par path — attendu).
  - `/signup`, `/demo`, `/pricing`, `/guides/rh-startup`, `/download`, `/auth/login`, `/contact`,
    `/privacy`, `/terms`, `/integrations`, `/checkout`, `/docs`, `/changelog` → 200.
  - `/download` : liens Firebase App Distribution réels + fallback `/signup?source=download_*` ✓
    (pas d'ancre morte). MAIS le `source=download_*` est **perdu** par SignupForm → **F-05 NOUVEAU**.
  - `x.com/leopardo_hr` → 404 (handle mort) → DÉJÀ COUVERT #2608 (sameAs).
  - Login démo web client : sélecteur de comptes OK (fallback local), tentative
    ahmed.benali → **« Server Error »** (500 API, cf. #2652).
- [x] **Admin Pages.dev live** : login OK, bouton « Accès Demo » (admin@leopardo-rh.com/password123)
  → « Erreur de connexion » (DÉJÀ COUVERT #2646). Lien mort `/vehicles` command palette
  (DÉJÀ COUVERT #2640/#2703).
- [x] **Builds locaux** : vitrine `npm run build` OK (69 pages, lint/tsc/jest OK) ;
  admin `npm run build` OK (lint 0 erreur) ; backend composer install OK.
- [x] **Suite de tests backend locale** (PostgreSQL, phpunit) : lancée sur main — ~490+ tests verts
  à la mi-course, quelques échecs isolés en cours d'analyse (à re-vérifier après run complet).

## B. Findings NOUVEAUX (cette vague → issues)

| ID | Sév | Surface | Constat | Preuve | Issue |
|----|-----|---------|---------|--------|-------|
| F-01 | P0 | API | `AuthService::login()` : lookup `public.user_lookups` pointant vers un schéma tenant absent → requête `employees` sur table inexistante → `QueryException` → **500** au lieu d'un 401 propre. Tout compte démo existant casse le login en prod (cf. #2652). Fix : lookup défensif (schéma inexistant = pas d'employé), erreur propre. | `api/app/Core/Auth/Infrastructure/Services/AuthService.php:52-68` (pas de garde `tenantEmployeesTableExists` avant la 1ʳᵉ requête) | #2652 (existant, non assignée) |
| F-02 | P1 | Docs/CI | `CHANGELOG.md` `[Unreleased]` : `### Added` ×2 consécutifs (l.75-76) + 5× `### Fixed` consécutifs → viole la garde maison `check-governance.ps1` (scanne le CHANGELOG sur chaque PR) → CI Governance devrait être rouge. | `CHANGELOG.md:74-77` (headers dupliqués) | **NOUVEAU** |
| F-03 | P2 | Admin | `AnalyticsView.vue` lit `alert.title`/`alert.description` alors que `/admin/dashboard/alerts` envoie `{id, level, message}` → alertes plateforme toujours vides de texte. | `front/admin-dashboard/src/views/analytics/AnalyticsView.vue:124-125` vs `api/app/Modules/Platform/.../PlatformAdminDashboardController.php:382-388` | **NOUVEAU** |
| F-04 | P3 | Admin | `CompanyDetailView.vue` : échecs API (détail, tickets, abonnement, modules) → `console.error` sans retour utilisateur → boutons silencieux. | `CompanyDetailView.vue:416-455` | **NOUVEAU** |
| F-05 | P2 | Web | `SignupForm.tsx:187` : `source: 'signup_form'` codé en dur → le paramètre `source=download_*` (fallback /download et liens guide) est perdu à la conversion (attribution lead cassée). | `front/web/src/modules/vitrine/components/forms/SignupForm.tsx:187` | **NOUVEAU** |
| F-06 | P3 | Web | Dépendance fantôme `zod` : importée par 3 route handlers (`api/billing/checkout`, `api/forms/contact`, `api/forms/demo`) mais absente de `package.json` → build fragile (dépend d'un hoisting transitoire). | `front/web/src/app/api/{billing/checkout,forms/contact,forms/demo}/route.ts` ; `front/web/package.json` sans `zod` | **NOUVEAU** |
| F-07 | P3 | Web | `api/forms/verify/route.ts` : pas de gate `areFormsEnabled()` (incohérent avec signup/demo/contact qui l'ont) → endpoint actif même site en maintenance. | `front/web/src/app/api/forms/verify/route.ts` vs `forms/_lib/lead-capture.ts` | **NOUVEAU** |
| F-08 | P3 | Web | Specs e2e périmées : `conversion-funnel.spec.ts` + `forms-and-submissions.spec.ts` attendent le champ mot de passe supprimé en v4.16.250 → échoueraient si rejouées. | `front/web/e2e/conversion-funnel.spec.ts`, `front/web/e2e/forms-and-submissions.spec.ts` | **NOUVEAU** |
| F-09 | P3 | Mobile | `leopardo_hr` : `main.dart` sans `SentryFlutter.init` (employee/manager/platform_admin l'ont) → crash non tracés sur l'app RH. | `front/mobile_apps/leopardo_hr/lib/main.dart` (0 référence Sentry) | **NOUVEAU** |

## C. Findings DÉJÀ COUVERTS (vérifiés, trace — pas de nouvelle issue)

| Constat (vérifié) | Issue(s) / PR existantes |
|-------------------|--------------------------|
| Login 500 comptes démo (live) | #2652 [P0] (implémenté dans cette vague, cf. tasks) |
| Comptes super-admin démo KO en prod | #2646, #2650 (+ PR #2773) |
| `/api-explorer` 500 en prod (fix présent sur main) | #2627/#2632/#2812 (déploiement) |
| `/blog` 404 vitrine (lien « Explore the blog ») | #2276, #2609 (+ PR #2774) |
| `x.com/leopardo_hr` 404 | #2608 |
| Actions rapides dashboard → `/dashboard/*` morts | #2655, #2603, #2777 |
| SignupForm 100 % FR | #2648 (+ PR #2775), #2727 |
| Nommage plans incohérent | #2649 (+ PR #2776), #2721, #2780 |
| Sitemap blog quand flag off | #2647 (+ PR #2774) |
| OG images `/og/*.png` inexistants | #2752, #2722 |
| PWA `/icon-192.png` absent + 14 vs 30 jours | #2756, #2724, #2753 |
| robots dupliqués + `/api/sitemap` fantôme | #2778 |
| Canonicaux hardcodés vercel.app | #2779, #2607 |
| Dashboard admin enveloppe `{data:[...]}` | #2747 (+ PR #2790) |
| UsersView pagination décorative | #2698, #2699, #2700, #2701 |
| Command palette `/vehicles` 404 | #2703, #2640 |
| MiniGlobe points fabriqués | #2696 |
| Maintenance simulée SystemAlertsOverlay | #2693, #2641 |
| Dead code admin + vitrine | #2612, #2771 (+ PR #2814), #2784 |
| Impersonation `/platform` vs `/admin` | #2785, #2624 |
| Header alertes + recherche | #2786, #2787, #2611 |
| SystemView sections non branchées | #2789, #2316 |
| Onboarding mobile POST vs PATCH (405) | #2794 |
| Cabinet manager navigation morte | #2748, #2735 |
| `/departments/{id}/hierarchy` 404 | #2594, #2633 |
| DZD en dur mobile | #2741 |
| Mojibake mobile | #2660, #2738 |
| Dette i18n mobile | #2755, #2740 |
| OpenAPI drift / RBAC matrix / docs périmées | #2638, #2662, #2675, #2757 |
| README stats périmées | #2772 (+ PR) |
| `source=download_*` — fallback /download ✓ OK, attribution perdue | F-05 (le fallback lui-même est sain) |
| Testimonials fabriqués vitrine | #2726 |
| `leopardo_marketing` orphelin | #2595, #2661 |
| `supported-countries` hardcodés admin | #2788 |
| 401 clé brute au lieu de localized_message | #2791, #2695 |
| `Math.random()` RevenueForecast | #2792 |
| Messages API accentués | #2793 |

## D. Méthode & périmètre

- Audit statique par surface + vérification runtime live (API/vitrine/admin) + builds locaux.
- Vérification ciblée des candidats P0/P1 par lecture du code (AuthService, api-explorer,
  payload alerts, onboarding mobile).
- Hors périmètre de cette vague (déjà tracées) : déploiements Render/Vercel (#2812/#2813),
  vagues payroll/CI, SSO/SEPA (vague 2 du registre omnichannel).

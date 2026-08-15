# Registre des manquements — QA Omnichannel 2026-08-15

> Session de test de la plateforme Leopardo RH (repo kitokoh/leopardo-hr, main @ 80c034ff).
> Mission : tester la vitrine, le web, l'admin, les mobiles, les workflows, les APIs, les
> logiques, l'onboarding et la cohérence — tout manquement → spec + tasks + issues
> (méthode Spec Kit) puis implémentation.
> Méthode : audits statiques 4 surfaces (API/web/admin/mobile) + tests runtime
> (build vitrine local, suite de tests backend locale, API Render live, admin Pages.dev live).

## A. Vérifications runtime effectuées

- [x] Build vitrine Next.js local sur main : `npm run build` → **OK (exit 0)**.
- [x] Vitrine locale (next start) : sitemap émet 10 URLs `/blog/*` alors que `/blog` → **404**
      (blog désactivé) → **BUG CONFIRMÉ** (`src/app/sitemap.ts:35`, `enableBlog` déstructuré jamais utilisé).
- [x] Vitrine locale : `/og/pricing.png` → **404**, aucun `public/og/` ni `public/og-image.png` → **BUG CONFIRMÉ**.
- [x] Vitrine locale : `/case-studies/{slug}` → 200 sur main (12 slugs OK) — les 404 live sont dus au **déploiement stale**.
- [x] Vitrine locale : `/dashboard/reports` et `/reports` → 307 login ; `/dashboard/reports` n'est PAS une route (build) → lien mort pour utilisateur connecté (home dashboard).
- [x] API Render live (`gestionemployerbackend.onrender.com`, health v4.23.5) :
      `/api/v1/i18n/catalog/fr` → **500** ; `/api/v1/supported-countries` → **404** ;
      `/api/v1/admin/dashboard/stats|activities|alerts` → **404** ; `/api/v1/admin/impersonations` → **404** ;
      `/api/v1/platform/impersonations` → **302** ; login invalide → `INVALID_CREDENTIALS` + `localized_message`.
      → **déploiement Render obsolète vs main** (routes ajoutées post-4.23.5).
- [x] Vitrine Vercel live : sitemap avec slugs blog ANCIENS (`paie-multi-pays-defis`, `pointage-biometrique-entreprise`)
      jamais présents dans `data/blog.ts` actuel ; `/case-studies/{slug}` 404 en live ; `/blog` 404.
      → **déploiement Vercel obsolète** (build précède #2281).
- [x] Admin Pages.dev live : page login OK ; bouton « Accès Démo » (admin@leopardo-rh.com/password123) → **échec**
      (démo désactivée en prod) avec message « Erreur de connexion. Vérifiez votre connexion internet. » trompeur.
- [x] Suite de tests backend (locale, Feature) : en cours — échec isolé `AbsenceApproveTest::manager can approve pending absence` à re-vérifier (flake/env).

## B. Findings — Vitrine web (front/web, Next.js)

| ID | Sév | Constat | Preuve |
|----|-----|---------|--------|
| W1 | P1 | Sitemap : 10 URLs `/blog/*` émises alors que `/blog` rend 404 (blog désactivé) — `enableBlog` déstructuré mais jamais utilisé | `src/app/sitemap.ts:35` vs `src/app/(landing)/blog/layout.tsx:24` (notFound) |
| W2 | P1 | Toutes les ogImages pointent vers `/og/<page>.png` + `/og-image.png` : dossier et fichiers inexistants → images de partage social 404 | `src/modules/vitrine/lib/seo.ts:25,93-340` ; `public/` sans `og/` |
| W3 | P2 | Ancres mortes page docs : `#webhooks-intro`, `#webhooks-events`, `#webhooks-testing` (ids inexistants) | `src/app/(landing)/docs/page.tsx:76-79` |
| W4 | P2 | Lien mort home dashboard : `/dashboard/reports` (route réelle `/reports`) | `src/app/(dashboard)/dashboard/page.tsx:542` |
| W5 | P2 | Service worker : precache de `/dashboard/attendance`, `/dashboard/absences`, `/dashboard/employees`, `/favicon.ico` inexistants → `cache.addAll` rejette → **install SW échoue** | `public/sw.js:14-17,25` |
| W6 | P2 | `/api/robots` déclare `Sitemap: .../api/sitemap` (route inexistante) ; robots dupliqués et divergents (`robots.ts` vs `/api/robots`) | `src/app/api/robots/route.ts:44` |
| W7 | P2 | Page dashboard edge-nodes appelle `/edge` (GET/POST/sync) : backend n'expose que `/platform/edge/nodes` → 404 systématique | `src/app/(dashboard)/edge-nodes/page.tsx:57,77,94` |
| W8 | P2 | Canonicaux hardcodés sur `https://gestionemployer-backend.vercel.app` (17+ fichiers + fallback og) au lieu de `NEXT_PUBLIC_SITE_URL` | `src/app/(landing)/{about,blog,blog/[slug],branding,careers,case-studies,changelog,checkout,contact,docs,download,faq,mobile,pricing,signup,testimonials,videos}/layout.tsx`, seo.ts |
| W9 | P2 | Pricing incohérents : FAQ cite plans « Starter »/« Business » fantômes ; Enterprise 299 €/mois au checkout vs « Sur devis » pricing ; checkout sans surcoût « +2 €/+4 €/employé » ; manifest « essai 14 jours » vs « 30 jours » partout | `data/faq.ts:164,167...`, `data/pricing.ts:93,179,265...`, `checkout/page.tsx:138-143,58-99`, `public/manifest.json:30` |
| W10 | P3 | CTA docs « Rejoindre les testeurs » → `/signup?source=download_*` au lieu des vrais liens Firebase App Distribution | `docs/page.tsx:447-449` vs `lib/mobile-download.ts` |
| W11 | P3 | Vidéos : client témoin « Atlas Industries » vs « Atlas Digital » (testimonials.ts) | `videos/page.tsx:47` |
| W12 | P3 | CTA Enterprise → `/contact?type=enterprise` mais la page contact lit `?topic=` (paramètre ignoré) | `pricing/page.tsx:718` vs `contact/page.tsx:28-37` |
| W13 | P3 | `vercel.json` : redirect mort `/old-page` → `/new-page` (ni l'un ni l'autre n'existe) | `vercel.json:73-74` |
| W14 | P3 | Logo structuré JSON-LD `…/logo.png` inexistant ; `apple-touch-icon` = SVG (iOS refuse) ; pas d'icônes PNG 192/512 → installabilité PWA compromise | `src/components/JsonLd.tsx:57`, `src/app/layout.tsx:94` |
| W15 | P3 | Code mort : `lib/mdx.ts` + `content/blog/*.mdx` inutilisés, exports orphelins (GradientOrbs, ScrollAnimations, Divider, useScrollAnimation, useFormSubmit, pageVariants, cn, generateOrganizationSchema, tout `lib/seo-metadata.ts`), 2 NewsletterForm dupliqués | grep imports |

## C. Findings — Admin dashboard (front/admin-dashboard, Vue 3)

| ID | Sév | Constat | Preuve |
|----|-----|---------|--------|
| A1 | P1 | Crash header/sidebar : le store stocke l'enveloppe `{data:[...]}` puis `.filter(...)` → TypeError `filter is not a function` | `stores/dashboard.js:46,60-61,85` vs `PlatformAdminDashboardController` (`['data'=>…]`) |
| A2 | P1 | `POST /admin/impersonations` → 404 (le backend expose `POST /platform/impersonations`) → impersonation #2518 inutilisable | `views/users/UsersView.vue:435` vs `api/routes/api.php:284-286` |
| A3 | P1 | Bouton « alertes critiques » du header : `showAlerts` togglé mais aucun panneau rendu | `components/layout/Header.vue:141,215` |
| A4 | P2 | Recherche globale : `console.log('Searching for:')` uniquement (« Implement search functionality ») | `components/layout/Header.vue:240` |
| A5 | P2 | MiniGlobe : 3 points codés en dur (Paris/Istanbul/Casablanca) affichés comme « Activité en temps réel », bouton « Actualiser » = mélange des points | `components/globe/MiniGlobe.vue:22,51-55,70-75` |
| A6 | P2 | `supportedCountries` codés en dur et incohérents entre écrans (6/12/7/10 pays) alors que `GET /supported-countries` existe | `views/settings/HolidaysView.vue:244`, `TaxSlabsView.vue:144`, `TaxRatesView.vue:237`, `SocialContributionsView.vue:201` |
| A7 | P2 | SystemView : 6 sections « Non disponible — aucun endpoint backend » en dur alors que les composants dédiés existent | `views/system/SystemView.vue:87-154` |
| A8 | P2 | UsersView : `per_page=100` sans page server-side ; pagination « client » plafonnée à 100 (pages 2+ = doublons, >100 invisibles) | `views/users/UsersView.vue:310-321` |
| A9 | P2 | CommandPalette : `vehicles` → `/vehicles` (route inexistante, la vraie est `/fleet`) ; `settings` → `/system` (la vraie est `/settings`) | `components/common/CommandPalette.vue:111,115` |
| A10 | P2 | Login : « Accès Démo » avec credentials en clair dans le bundle ; bouton affiché même si `DEMO_MODE_ENABLED=false` → échec + message « vérifiez votre connexion internet » trompeur | `views/auth/LoginView.vue:122-129,191-207` |
| A11 | P2 | 401 `INVALID_CREDENTIALS` : la clé brute est affichée au lieu de `localized_message` ; reload complet au lieu d'une navigation SPA | `stores/auth.js:72`, `services/api.js:185` |
| A12 | P2 | 12 routes (payroll, leaves, contracts, recruitment, training, fleet, chat, webhooks, exports, reports, predictions, audit) neutralisées par le guard `requiresTenant` → vues construites mais inaccessibles | `router/index.js:154-291,392-395` |
| A13 | P3 | `holidays.nav.title` : clé i18n absente → H1 affiche la clé brute | `router/index.js:335` vs `i18n/locales/*.json` |
| A14 | P3 | Carte dashboard « Préparer intégrations partenaires » → `/webhooks` (route guard-blockée) | `views/DashboardView.vue:336` |
| A15 | P3 | `SystemAlertsOverlay` « Désactiver (maintenance) » = `// Simulate API call` (setTimeout, aucun endpoint) ; bannière jamais affichée | `components/alerts/SystemAlertsOverlay.vue:207` |
| A16 | P3 | `RevenueForecastWidget` : historique généré avec `Math.random()` (données synthétiques) | `components/analytics/RevenueForecastWidget.vue:187` |
| A17 | P3 | 12 composants + 8 widgets analytics + 3 composables jamais importés (dead code) | grep |
| A18 | P3 | Messages non accentués systématiques (« Session expiree », « Donnees invalides ») | `services/api.js:27-44` |
| A19 | P3 | `confirm()`/`prompt()` natifs + libellés anglais dans UI française | `views/growth/GrowthDashboardView.vue:161,167` |
| A20 | P3 | Filtre « En attente » proposé mais désactivé dans le code ; refs mortes `showCreateModal`/`showEditModal`/`loadCompanies` | `views/users/UsersView.vue:240-241,275,308,335` |

## D. Findings — Mobile (front/mobile_apps, Flutter)

| ID | Sév | Constat | Preuve |
|----|-----|---------|--------|
| M1 | P1 | Manager : navigation Placard vers `/cabinet/folder/${folder.id}` mais routeur déclare `/cabinet/:folderId` → GoRouter `No route found` (crash au clic) | `leopardo_manager/lib/features/cabinet/screens/cabinet_screen.dart:432` vs `leopardo_manager/lib/app.dart:179` |
| M2 | P1 | Onboarding Employee/HR : `completeStep(int)`/`skipStep(int)` → `POST /onboarding-setup/{id}/complete|skip` alors que le backend expose **PATCH** `/onboarding-setup/{stepKey}/complete|skip` (stepKey = string) → 405+404 ; le middleware `api.manager` → 403 pour un employé | `leopardo_employee/lib/features/onboarding/data/onboarding_repository.dart:23-35`, `leopardo_hr/…:23-35`, `api/routes/modules/billing.php:22-26` |
| M3 | P1 | App Manager : employé non-manager/RH authentifié → boucle de redirection `/welcome` ↔ `/` (GoRouter Infinite redirect → crash) ; même pattern app HR | `leopardo_manager/lib/app.dart:83-89`, `leopardo_hr/lib/app.dart:83-88` |
| M4 | P2 | Route `/onboarding` déclarée dans 3 apps mais aucun code n'y navigue → flux onboarding inaccessible | `app.dart:196/219/209` (0 occurrence de navigation) |
| M5 | P2 | `MobileExperienceService::stageFor()` lit `extra_data.app_actions_count` jamais écrit nulle part → stage `new` permanent (home réduite à 3 chips) | `api/app/Modules/HR/Infrastructure/Services/MobileExperienceService.php:70` (grep global : seule lecture) |
| M6 | P2 | `appContextFor()` déclare des apps inexistantes (`comptable`, `dept`, `marketing` non distribuée) avec deep_link_scheme jamais consommé par aucun client | `MobileExperienceService.php:42-58` (0 hit Dart) |
| M7 | P2 | Contrats mobiles (`dev-hub/tools/mobile-workflow-contracts.json`) : `hr.workflows` vidé par #2247 ; `manager_dashboard_placeholder` référence route/écran inexistants | contrat vs `leopardo_manager/lib/` |
| M8 | P2 | `/onboarding-setup/*` derrière `api.manager` → l'écran onboarding employee serait 403 | `api/routes/modules/billing.php:22` |
| M9 | P3 | `EnsureAppContextMiddleware` enregistré mais jamais attaché à une route ; aucune app n'envoie `X-App-Context` | `api/bootstrap/app.php:100` (grep routes = 0) |
| M10 | P3 | `getDepartmentHierarchy()` → `/departments/{id}/hierarchy` inexistant (seul `/org-chart` existe) — méthode jamais appelée | `leopardo_manager/lib/features/organigramme/data/organigramme_repository.dart:61` |
| M11 | P3 | `int.parse(pathParameters['folderId']!)` : crash sur deep-link non numérique | `app.dart` manager:181, employee:153, hr:155 |
| M12 | P3 | Routes mortes déclarées jamais naviguées : `/training`, `/expenses`, `/ai-chat`, `/ai-voice`, `/vehicle-map` (employee), `/modules/rh` (manager/hr) ; `PersonalSpaceScreen` non routé ; `suggested_home_route` jamais consommé | grep |

## E. Findings — API backend (api/, Laravel)

| ID | Sév | Constat | Preuve |
|----|-----|---------|--------|
| P1 | P2 | 8 outils IA enregistrés actifs mais non implémentés (`get_attendance_today`, `get_attendance_anomalies`, `get_monthly_report`, `get_absences`, `get_daily_summary`, `get_notifications`, `get_leave_balances`, `get_payroll_summary`) → réponse « registered but not yet implemented » | `database/seeders/AIToolRegistrySeeder.php:73-187` vs `app/AI/IntentEngine.php:168-173` |
| P2 | P2 | `POST /admin/ai/chat` : validation + réponse codée en dur « assistant non disponible » (200 OK qui ne fait rien) | `PlatformAdminAiConversationController.php:74-100` |
| P3 | P2 | Catch silencieux `catch (\Throwable) { return ['data' => []]; }` sans log (liste conversations IA + fleet alerts) → super-admin voit « aucune donnée » au lieu d'une erreur | `PlatformAdminAiConversationController.php:41,113`, `PlatformAdminFleetAlertController.php:65` |
| P4 | P2 | Route dupliquée `POST /webhooks/{webhookEndpoint}/test` (2 déclarations, la 1ʳᵉ morte) | `routes/modules/hr_extended.php:158,166` |
| P5 | P3 | Routes Growth sans throttle (tous les autres modules en ont) | `routes/modules/growth.php:23-44` |
| P6 | P3 | Callbacks SSO publics SAML/OIDC sans rate-limit ; `{companyId}` SAML sans `whereUuid` (incohérent avec OIDC) | `routes/modules/sso.php:16-28` |
| P7 | P3 | `ProvisionDemoTenantJob` : TODO « magic link » jamais généré/ envoyé | `app/Jobs/ProvisionDemoTenantJob.php:55` |
| P8 | P3 | `GET /ai/agent/workflows` liste codée en dur ; workflow `new_employee_onboarding` sans endpoint correspondant | `app/Http/Controllers/AI/AgentController.php:56-80` |
| P9 | P3 | Fake employee `alice@demo.local` / `password` créé dans chaque tenant trial (chemin prod) | `app/Modules/Billing/Application/Actions/ProvisionGuidedTrial.php:150-176` |
| P10 | P3 | `CommunicationService` : tout provider non implémenté bascule silencieusement sur audit (délivrance jamais réelle) | `CommunicationService.php:404`, `config/communication.php:33-44` |
| P11 | P3 | `PayrollCycleController::complianceFor()` avale toutes les exceptions → conformité multi-pays disparaît silencieusement | `PayrollCycleController.php:254-267` |
| P12 | P3 | 135 routes implémentées absentes de l'OpenAPI (clusters : user/*, public/careers/*, trial/*, hr/*, departments/*, announcements/*, conversations/*, payrolls/*, reports/*, webhooks Stripe/Chargily/email-bounce, platform/*, ai/*, edge/*, notifications/*, health/live|ready, admin/social-contributions/*) | `api/openapi.yaml` vs `routes/` |
| P13 | P3 | `/billing/checkout` et `/billing/portal` → 503 tant que `services.stripe.secret` absent | `BillingController.php:195` |

## F. Findings — Déploiement / ops (live)

| ID | Sév | Constat | Preuve |
|----|-----|---------|--------|
| D1 | P1 | API Render live v4.23.5 obsolète vs main : `/i18n/catalog/fr` → 500, `/supported-countries` → 404, `/admin/dashboard/*` → 404, `/admin/impersonations` → 404, `/platform/impersonations` → 302 sans auth | curl live 2026-08-15 |
| D2 | P1 | Vitrine Vercel obsolète : sitemap blog avec slugs disparus, `/case-studies/{slug}` 404, build précède #2281 | curl live 2026-08-15 |
| D3 | P2 | Bouton « Accès Démo » admin affiché alors que démo désactivée en prod → échec avec message trompeur | Pages.dev live |

## G. Déjà couvert (NE PAS dupliquer)

- SSO SAML 501, SEPA IBAN placeholder, export history, notification push, routes mortes backend → spec `qa-hardening-wave-2-2026-08-14` (21 tasks ouvertes, non implémentées à ce jour).
- 16 opérations OpenAPI sans route, mismatch verbes, EdgeController mort, LogsView admin orpheline → spec `qa-web-openapi-wave-2026-08-14`.
- Open issues #2590, #2587, #2586, #2583, #2580 (payroll CI/PHPStan/CHANGELOG/correlation) → à traiter hors scope.

# Tasks: Vague QA Omnichannel 2026-08-15 — manquements NOUVEAUX (dédupliqués)

**Input**: spec.md + findings-registry.md

**Prerequisites**: spec.md (required)

> **Anti-doublon (Constitution §VII + #2400)** : les findings déjà couverts par les vagues
> parallèles (`qa-audit-2026-08-15`, `qa-audit-expert-{web,mobile,backend}-2026-08-15`) sont
> référencés, PAS dupliqués (voir table en fin de fichier). #2276 et #2274 ont été **réouvertes**
> (fermetures sans fix effectif sur main). Numérotation continue à partir de T099 (max existant : T098).

## Phase 1 — Vitrine web (US1)

- [ ] T099 [P1] [US1] `src/app/(dashboard)/dashboard/page.tsx:542` : `href="/dashboard/reports"` → `/reports` (route réelle du groupe `(dashboard)`).
- [ ] T100 [P2] [US1] Robots dupliqués : supprimer `src/app/api/robots/route.ts` (déclare `Sitemap: /api/sitemap` inexistant) et garder `src/app/robots.ts` canonique.
- [ ] T101 [P2] [US1] Canonicaux : remplacer le domaine en dur `https://gestionemployer-backend.vercel.app` par `NEXT_PUBLIC_SITE_URL` dans les layout.tsx vitrine + `seo.ts` (17+ fichiers, cf. registry W8).
- [ ] T102 [P2] [US1] Pricing/checkout : retirer les plans fantômes « Starter »/« Business » des FAQ (4 locales) ; checkout Enterprise → « Sur devis » (cohérent avec pricing) ; afficher le surcoût par employé actif au checkout (Pilot/Operations).
- [ ] T103 [P3] [US1] `videos/page.tsx:47` : « Atlas Industries » → « Atlas Digital » (source `data/testimonials.ts`).
- [ ] T104 [P3] [US1] `pricing/page.tsx:718` : CTA Enterprise → `/contact?topic=enterprise` (la page contact lit `?topic=`, pas `?type=`).
- [ ] T105 [P3] [US1] `src/components/JsonLd.tsx:57` : logo `${SITE_URL}/logo.png` → fichier réel dans `public/` (ou retirer du schéma).
- [ ] T106 [P3] [US1] Dead code vitrine : supprimer `lib/mdx.ts` + `content/blog/*.mdx` inutilisés, exports orphelins (GradientOrbs, ScrollAnimations, Divider, useScrollAnimation, useFormSubmit, pageVariants, cn, generateOrganizationSchema, `lib/seo-metadata.ts`), dédupliquer `NewsletterForm` (src/components vs modules/vitrine).

## Phase 2 — Admin dashboard (US2)

- [ ] T107 [P1] [US2] Impersonation : `views/users/UsersView.vue:435` → `POST /platform/impersonations` (contrat réel `routes/api.php:284-286`) — la feature #2518 est inutilisable actuellement.
- [ ] T108 [P1] [US2] `components/layout/Header.vue:141,215` : rendre le panneau « alertes critiques » branché sur `systemAlerts` quand `showAlerts` est vrai (bouton actuellement sans effet visible).
- [ ] T109 [P2] [US2] `components/layout/Header.vue:240` : recherche globale → filtrage réel de la navigation ; retirer le `console.log` (« Implement search functionality »).
- [ ] T110 [P2] [US2] Pays supportés : remplacer les 4 tableaux codés en dur incohérents (6/12/7/10 pays) par `GET /supported-countries` (source unique) dans HolidaysView/TaxSlabsView/TaxRatesView/SocialContributionsView.
- [ ] T111 [P2] [US2] `views/system/SystemView.vue:87-154` : brancher les 6 sections sur les endpoints réels (`/platform/observability/queues`, `/platform/observability/notifications`, `/metrics`…) — retirer « Non disponible » en dur.
- [ ] T112 [P2] [US2] 401 : `stores/auth.js:72` + `services/api.js:185` — afficher `localized_message` du backend (plus la clé brute `INVALID_CREDENTIALS`) ; navigation SPA vers `/login` (plus `window.location`).
- [ ] T113 [P3] [US2] `components/analytics/RevenueForecastWidget.vue:187` : retirer `Math.random()` (données réelles ou widget retiré).
- [ ] T114 [P3] [US2] `services/api.js:27-44` : messages accentués (« Session expirée », « Données invalides », « Réessayez plus tard »).

## Phase 3 — Mobile (US3)

- [ ] T115 [P1] [US3] Onboarding Employee/HR : `onboarding_repository.dart` (2 apps) — `PATCH /onboarding-setup/{stepKey}/complete|skip` avec la clé string (au lieu de POST + id numérique → 405/404) ; rendre l'accès employé possible (middleware, cf. T118).
- [ ] T116 [P1] [US3] Apps Manager/HR : corriger la boucle de redirection infinie (non-manager/non-RH authentifié → `/welcome` ↔ `/`) — écran « accès refusé » ou redirection contrôlée, jamais de boucle GoRouter.
- [ ] T117 [P2] [US3] Route `/onboarding` (3 apps) : la rendre atteignable depuis l'UI (entrée home/profil quand le checklist est incomplet) — aujourd'hui déclarée mais injoignable.
- [ ] T118 [P2] [US3] Backend onboarding : `api/routes/modules/billing.php:22-26` — middleware adapté pour que l'app Employee puisse compléter son onboarding (403 actuel `api.manager`).
- [ ] T119 [P2] [US3] `MobileExperienceService::stageFor()` (l.70) : source de vérité du stage — écrire `app_actions_count` (ou critère calculable) ; le stage `regular` doit être atteignable (actuellement `new` permanent).
- [ ] T120 [P2] [US3] `MobileExperienceService::appContextFor()` (l.42-58) : retirer les apps inexistantes (`comptable`, `dept`, `marketing` non distribuée) ou ne les servir que si réelles ; documenter les deep links consommés.
- [ ] T121 [P3] [US3] `int.parse(pathParameters['folderId']!)` (3 apps) : garde pour deep-link non numérique (fallback, pas de crash).
- [ ] T122 [P3] [US3] Routes mortes : `/training`, `/expenses`, `/ai-chat`, `/ai-voice`, `/vehicle-map` (employee), `/modules/rh` (manager/hr) — retirer ou brancher sur des écrans réels ; `PersonalSpaceScreen` routé ou supprimé ; `suggested_home_route` consommé ou retiré du contrat API.

## Phase 4 — API backend (US4)

- [ ] T123 [P2] [US4] Outils IA : implémenter les 8 outils lecture dans `app/AI/IntentEngine.php` (scoping employé) ou retirer les outils non implémentés du seeder — plus de « registered but not yet implemented » via `GET /ai/tools`.
- [ ] T124 [P2] [US4] `PlatformAdminAiConversationController.php:74-100` : `POST /admin/ai/chat` — traitement réel ou 501 explicite documenté ; jamais de 200 factice.
- [ ] T125 [P2] [US4] Catch silencieux : `PlatformAdminAiConversationController.php:41,113` + `PlatformAdminFleetAlertController.php:65` — logger et retourner 5xx explicite (plus `{data:[]}` sans log).
- [ ] T126 [P2] [US4] `routes/modules/hr_extended.php:158,166` : supprimer la déclaration dupliquée `POST /webhooks/{webhookEndpoint}/test`.
- [ ] T127 [P3] [US4] `routes/modules/growth.php:23-44` : ajouter les throttles (`throttle:api` / `throttle:platform-sensitive`) alignés sur les autres modules.
- [ ] T128 [P3] [US4] `routes/modules/sso.php:16-28` : throttle sur callbacks publics + `whereUuid` sur `{companyId}` SAML (incohérent avec OIDC).
- [ ] T129 [P3] [US4] `AgentController.php:56-80` : retirer le workflow fantôme `new_employee_onboarding` ou l'implémenter.
- [ ] T130 [P3] [US4] `ProvisionGuidedTrial.php:150-176` : fake employee `alice@demo.local`/`password` — créer uniquement si `DEMO_MODE_ENABLED`, jamais sur le chemin trial public par défaut.
- [ ] T131 [P3] [US4] `CommunicationService.php:404` : warning explicite + statut `undelivered` quand un provider bascule silencieusement sur audit.
- [ ] T132 [P3] [US4] `PayrollCycleController.php:254-267` : logger l'exception au lieu d'avaler en `[]`.

## Phase 5 — Ops & déploiement (US5)

- [ ] T133 [P1] [US5] Déployer l'API Render sur une version ≥ main (résout `/i18n/catalog/fr` 500, `/supported-countries` 404, `/admin/dashboard/*` 404, `/admin/impersonations` 404) — checklist post-deploy curl incluse.
- [ ] T134 [P1] [US5] Déployer la vitrine Vercel sur le build main courant (résout sitemap stale, `/case-studies/{slug}` 404 live) — checklist post-deploy.

## Table anti-doublon (findings couverts par les vagues parallèles — NE PAS recréer)

| Finding (registry) | Issue(s) existante(s) |
|---|---|
| W2 og images | #2722, #2752 |
| W5 sw precache | #2723 |
| W7 edge-nodes | #2602 (résolution : retirer la page) |
| W9 essai 14j vs 30j / meta desc | #2753, #2721 |
| W10 liens testeurs mobiles | #2733 |
| W13 vercel.json | #2732 |
| W14 icônes PWA | #2756 |
| A1 dashboard.js envelope | #2747 |
| A5 MiniGlobe | #2696 |
| A8 UsersView pagination | #2698 |
| A9 CommandPalette | #2703 |
| A10 LoginView démo/credentials | #2695, #2730 |
| A13 clés i18n titres | #2708 |
| A14 DashboardView carte | #2704 |
| A15 SystemAlertsOverlay | #2693 |
| A19 confirm/alert Growth | #2711 |
| A20 filtre « En attente » | #2699 |
| M1 cabinet manager | #2735, #2748 |
| M7 contrats mobiles | #2754 |
| M10 hierarchy endpoint | #2594 (résolution : créer la route) |
| P7 magic link | #2620 |
| P12 OpenAPI | #2662, #2674 |
| T001 sitemap blog | #2276 (RÉOUVERTE — régression) |
| T003 ancres docs | #2274 (RÉOUVERTE — résidu) |
| T031 dead code admin | #2771 (créée par cette vague) |

## Dependencies

- US1 (T099-T106) : indépendante — bloc vitrine (front/web).
- US2 (T107-T114) : indépendante — bloc admin (front/admin-dashboard).
- US3 (T115-T122) : indépendante — bloc mobile (front/mobile_apps + api pour T118/T119/T120).
- US4 (T123-T132) : indépendante — bloc API (api/).
- US5 : T133/T134 dépendent des accès de déploiement (hors sandbox).

## Parallel execution examples

- Worker A : T099-T106 (vitrine) — branches `fix/<issue>-<slug>` dans front/web.
- Worker B : T107-T114 (admin) — front/admin-dashboard.
- Worker C : T123-T132 (API) — api/.
- Worker D : T115-T122 (mobile) — front/mobile_apps + api (T118-T120).

## Implementation strategy

- MVP P1 : T099, T107, T108, T115, T116 (5 issues P1) — livrés en premier.
- Vague 2 : P2/P3 par domaine, en parallèle.
- Validation : build vitrine + lint admin + PHPStan strict + tests ciblés (backend) ;
  CI mobile (Flutter) comme porte finale pour US3.

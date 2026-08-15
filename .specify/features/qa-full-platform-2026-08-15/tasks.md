# Tasks: QA Full Platform — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

**PRs** (une par surface, chacune `Closes #<issue>` + CHANGELOG) :
- `fix/2652-2654-2662-api-hardening` — backend (US1, US2, US3, US11)
- `fix/2655-2657-web-vitrine` — web (US4, US5, US6)
- `fix/2658-2659-admin-dashboard` — admin (US7, US8)
- `fix/2660-2661-mobile` — mobile (US9, US10)

## Phase 1 — US1 Login API sans 500 (P1) — issue #2652

- [ ] T001 [US1] `AuthService::login()` : garde `password_hash` null → `InvalidCredentialsException` (avant `Hash::check`) dans les deux chemins (tenant lookup + public)
- [ ] T002 [US1] `AuthService` : blindage `locked_until` (vérifier `$employee->locked_until instanceof CarbonInterface` avant `->isFuture()`), `supportsLoginLocking` inchangé
- [ ] T003 [US1] `AuthService` : try/catch ciblé autour du bloc lookup tenant → `InvalidCredentialsException` (jamais QueryException/TypeError remontée) — log contextuel (Sentry) sans fuite
- [ ] T004 [US1] Tests `tests/Feature/Auth/LoginFlowTest.php` : (a) mauvais password → 401 ; (b) `password_hash` null → 401 ; (c) `locked_until` invalide → 401 ; (d) lookup vers schéma inexistant → 401 (ou 5xx shape), jamais HTML/`Server Error` ; (e) login OK → 200 + token (contrôle)
- [ ] T005 [US1] Vérification : `php artisan test --filter=LoginFlowTest`, PHPStan strict, Pint

## Phase 2 — US2 Contrat d'erreur API (P1) — issue #2653

- [ ] T006 [US2] `bootstrap/app.php` : renderer `AuthenticationException` pour `api/*` → 401 `{error:"UNAUTHENTICATED", message, localized_message}` (jamais de redirect HTML)
- [ ] T007 [US2] `bootstrap/app.php` : renderer générique `Throwable` pour `api/*` → 500 `{error:"INTERNAL_ERROR", message:"INTERNAL_ERROR", localized_message}` + `Log::error`/Sentry (message d'exception jamais exposé)
- [ ] T008 [US2] Unifier les 404 : le renderer `ModelNotFoundException` et `HttpExceptionInterface` 404 → `__('errors.NOT_FOUND')` (même localized_message français)
- [ ] T009 [US2] `OnboardingController` : réponse d'erreur `INVITATION_NOT_FOUND` → code string plat `{error: "INVITATION_NOT_FOUND", ...}` (shape contrat)
- [ ] T010 [US2] Tests `tests/Feature/Api/ErrorContractTest.php` : 401 sans Accept sur route api (JSON), 500 shape pour exception non mappée (mock), 404 unifiée, onboarding token invalide
- [ ] T011 [US2] Vérification : tests + PHPStan + Pint verts

## Phase 3 — US3 Routes runtime réparées (P1) — issue #2654

- [ ] T012 [US3] `EdgeNodeController::sync(int $nodeId)` : implémentation (vérifie nœud tenant + licence, délègue à `SyncEngineService`/`EdgeDaemonSyncClient`, retourne SyncLog JSON ou 404/409 conforme)
- [ ] T013 [US3] `hr_extended.php` : suppression du doublon `POST /webhooks/{webhookEndpoint}/test` (ligne ~168), commentaire sur la définition canonique
- [ ] T014 [US3] `I18nCatalog` : `readLocale`/`readVersions` → exceptions métier typées (`I18nCatalogUnavailableException` — fichier/JSON manquant/corrompu)
- [ ] T015 [US3] `TranslationCatalogController` : catch des exceptions i18n → 503/404 shape contrat (jamais HTML), log
- [ ] T016 [US3] Tests : `EdgeNodeSyncTest` (sync OK / nœud inconnu / non-tenant), `WebhookControllerTest` (test endpoint), `TranslationCatalogControllerTest` (catalogue absent → 503 shape ; présent → 200)
- [ ] T017 [US3] Vérification : `route:list` sans doublon, tests + PHPStan + Pint

## Phase 4 — US4 Dashboard web sans URLs mortes (P1) — issue #2655

- [ ] T018 [US4] `front/web/src/app/(dashboard)/dashboard/page.tsx` : href `/dashboard/reports|employees|absences` → `/reports|employees|absences` (carte Rapports + actions rapides)
- [ ] T019 [US4] `front/web/public/sw.js` : précache corrigé (URLs réelles)
- [ ] T020 [US4] `front/web/e2e/dashboard-quick-actions.spec.ts` : assertions de contenu cible (pas seulement `toHaveURL`)
- [ ] T021 [US4] Vérification : `rg '/dashboard/(reports|employees|absences|attendance)'` = 0 dans page.tsx/sw.js/e2e ; lint + tsc

## Phase 5 — US5 SEO/canonicals (P1) — issue #2656

- [ ] T022 [US5] Centraliser `SITE_URL` : nouveau helper `src/lib/site-url.ts` (env `NEXT_PUBLIC_SITE_URL` prioritaire, fallback domaine de marque) ; remplacer les 8 occurrences (JsonLd, layout, sitemap, robots, seo-metadata, structured-data, BlogArticle, seo.ts)
- [ ] T023 [US5] Layout racine : retirer `canonical: "/"` global ; canonical par page via metadata
- [ ] T024 [US5] `seo.ts` : description pricing alignée (Free / Pilot 29€ / Operations 99€ / Enterprise)
- [ ] T025 [US5] `sitemap.ts` : URLs blog conditionnées à `NEXT_PUBLIC_ENABLE_BLOG`
- [ ] T026 [US5] Vérification : `rg 'gestionemployer-backend.vercel.app|localhost:3000'` = 0 dans les fallbacks ; lint + build

## Phase 6 — US6 Web i18n & hygiène (P2) — issue #2657

- [ ] T027 [US6] `layout.tsx` : `lang` SSR depuis la locale (au lieu de `fr` hardcodé)
- [ ] T028 [US6] `vercel.json` : suppression du redirect placeholder `/old-page` → `/new-page`
- [ ] T029 [US6] Dates blog `src/content/blog/*.md` : mise à jour (ou mention « archivé » explicite)
- [ ] T030 [US6] Vérification : lint + build verts

## Phase 7 — US7 Admin : composants morts, erreurs, e2e (P1) — issue #2658

- [ ] T031 [US7] Supprimer `EditUserModal.vue` (mock) + `CreateUserModal.vue` + les 8 widgets analytics orphelins + 9 composants system orphelins (vérification imports avant suppression)
- [ ] T032 [US7] `EdgeNodesView.vue` + `stores/edgeNodes.js` : catch + état d'erreur UI (toast/bandeau)
- [ ] T033 [US7] `e2e/dashboard-kpi.spec.js` : retirer l'attente du lien « Mot de passe oublie » absent (ou le tester contre l'aria-label réel)
- [ ] T034 [US7] `e2e/platform-auth-smoke.spec.js` : asserter le contenu réel du dashboard post-login
- [ ] T035 [US7] `e2e/social-contributions.spec.js` + `e2e/tax-slabs.spec.js` : mocks de route ou skip si `E2E_BACKEND_URL` absent
- [ ] T036 [US7] Vérification : lint 0 erreur, `rg 'Simulate API' src` = 0, playwright local vert (specs mockées)

## Phase 8 — US8 Admin base URL (P2) — issue #2659

- [ ] T037 [US8] `src/services/api.js` : fallback par défaut = URL de production (env prioritaire) ; localhost réservé au dev explicite
- [ ] T038 [US8] `.github/workflows/deploy-admin-dashboard.yml` : `VITE_API_URL` injecté au build (secret/env repo)
- [ ] T039 [US8] Aligner `README.md`, `.env.example`, `public/_headers` (une seule vérité)
- [ ] T040 [US8] Vérification : lint + build admin

## Phase 9 — US9 Mobile mojibake (P1) — issue #2660

- [ ] T041 [US9] Ré-encoder `leopardo_employee/lib` (4 fichiers : smart_attendance_screen, attendance_mode_picker_screen, evaluation_list_screen, personal_space_screen + profile_screen/settings_screen arabe)
- [ ] T042 [US9] Ré-encoder `leopardo_manager/lib` (4 fichiers)
- [ ] T043 [US9] Vérification : `rg mojibake` = 0 sur `lib/` des 5 apps

## Phase 10 — US10 Mobile intégration marketing + parité (P2) — issue #2661

- [ ] T044 [US10] `melos.yaml` : ajouter `leopardo_marketing` aux packages
- [ ] T045 [US10] `front/mobile_apps/README.md` + `docs/mobile/README.md` : liste 6 apps
- [ ] T046 [US10] `mobile-apps-ci.yml` : analyse marketing ; `mobile-distribute.yml` + `mobile-distribute-main.yml` : parité 4 apps (+ hr)
- [ ] T047 [US10] `validate-mobile-apps-split.ps1` : inclure leopardo_hr et leopardo_marketing
- [ ] T048 [US10] `leopardo_marketing/pubspec.yaml` : description propre, flutter_lints ^6.0.0 ; `platform_admin/pubspec.yaml` : flutter_lints ^6.0.0
- [ ] T049 [US10] (Chantier documenté) migration des 13 repositories dupliqués vers leopardo_core — note dans README mobile

## Phase 11 — US11 OpenAPI surface documentée (P2) — issue #2662

- [ ] T050 [US11] `api/openapi.yaml` : documenter le CRUD `/admin/social-contributions` (+ `/admin/tax-slabs` si manquant) — retirer les doublons stale racine
- [ ] T051 [US11] `api/openapi.yaml` : documenter les routes à forte valeur `GET /reports/*` (échantillon prioritaire) et `/user/*`
- [ ] T052 [US11] `check-openapi-route-coverage.py` : étendre le parseur aux fichiers `app/Modules/*/routes/*.php` (EdgeSync/SmartAttendance)
- [ ] T053 [US11] Mettre à jour `openapi-coverage-allowlist.txt` (retirer les routes désormais documentées)
- [ ] T054 [US11] Vérification : script de coverage CI vert, `info.version` inchangé ou bumpé

## Phase 12 — Polish & Cross-Cutting

- [ ] T055 CHANGELOG.md : une entrée par PR sous `## [Unreleased]`
- [ ] T056 AGENTS.md : mise à jour si leçon opérationnelle (ex. garde « API base URL jamais localhost en prod »)
- [ ] T057 Vérification finale globale : lint/builds web+admin, tests backend ciblés, smoke live après déploiement

## Dependencies & Execution Order

- **Phase 1 (US1)** bloque rien ; **Phase 2 (US2)** indépendante mais même PR backend ; **Phase 3 (US3)** même PR ; **Phase 11 (US11)** même PR backend (openapi).
- **Phase 4-6 (web)** : une PR, ordre 4→5→6 (fichiers disjoints).
- **Phase 7-8 (admin)** : une PR, 7 puis 8.
- **Phase 9-10 (mobile)** : une PR, 9 puis 10.
- Toutes les phases sont indépendantes entre PRs (fichiers disjoints) → parallélisable.
- Aucune dépendance inter-PR. Chaque PR contient son CHANGELOG + `Closes #<issue>`.

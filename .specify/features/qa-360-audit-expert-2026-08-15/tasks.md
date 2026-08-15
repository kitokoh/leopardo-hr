# Tasks — QA 360° Audit Expert 2026-08-15 (manquements nouveaux)

> Format strict : `- [ ] [TaskID] [P?] [Story?] Description avec chemin de fichier`.
> Chaque tâche → une issue GitHub `T###: <description>` (protocole Spec Kit).

## API / Backend

- [ ] T001 [P2] [US1] Garde OAuth Google : `api/app/Core/Auth/Interfaces/Api/V1/AuthController.php:196-207` — pas d'auto-provision de compte `ordinary` actif sans invitation (#2617) ni feature gate ; 401 `UNKNOWN_ACCOUNT` aligné sur `handleGoogleToken`.
- [ ] T002 [P3] [US1] Sanitiser les messages d'erreur bruts (~10 sites : EmployeeImportController:147, BulkPaymentController:122, WebhookController:226, TaxSlabController:164, SocialContributionController:157, AuthController:192,246, RateValidationAdminController:69,94) → codes d'erreur stables, détails en log serveur.
- [ ] T003 [P3] [US1] Import CSV employés : catch unique-violation (SQLSTATE 23505) → skip ligne + 422, jamais 500 (`EmployeeImportController.php:110-152`).
- [ ] T004 [P3] [US1] Scope `BelongsToCompany` fail-closed quand `current_company` absent (`api/app/Shared/Traits/BelongsToCompany.php:17-20`) + filtre explicite `WebhookController::index`.

## Vitrine Web

- [ ] T005 [P2] [US2] Réaligner `front/web/e2e/navigation-and-links.spec.ts:16-49` (+ conversion-funnel:157-160, dark-mode-toggle:184-187) sur la navbar réelle ; remplacer les gardes `isVisible()` par des assertions dures.
- [ ] T006 [P2] [US2] `front/web/public/sw.js:63-73` : ne pas mettre en cache les routes authentifiées (/dashboard, /payroll, /employees…) ; cleanup de `setInterval(update(), 60s)` dans `PWAProvider.tsx:56-58`.
- [ ] T007 [P2] [US2] `/mobile` : piloter le body par `useVitrineLocale` (fin du useState FR en dur) — `front/web/src/app/(landing)/mobile/page.tsx:249-270`.
- [ ] T008 [P2] [US2] OG/Twitter sur les guides (×3) + /demo via `generateSEOMetadata` + images `public/og/guides-*.png`.
- [ ] T009 [P3] [US2] A11y FAQ : label/aria-label input recherche, aria-expanded accordéons ; Navbar drawer aria-label localisé + aria-expanded mobile (`faq/page.tsx:109,139`, `Navbar.tsx:379,406`).
- [ ] T010 [P3] [US2] Supprimer `front/web/src/modules/vitrine/lib/seo-metadata.ts` (mort, divergent) après report éventuel dans `seo.ts`.
- [ ] T011 [P3] [US2] Footer : modéliser les liens `{label, href}` par locale au lieu de l'index positionnel (`Footer.tsx:54`).
- [ ] T012 [P3] [US2] Navbar : `router.replace(?lang=)` sur changement de langue (`Navbar.tsx:333-346,389-396`).

## Admin Dashboard

- [ ] T013 [P2] [US3] `front/admin-dashboard/src/stores/realtime.js:354,365` : PUT → PATCH `/notifications/{id}/read` et POST `/notifications/read-all`.
- [ ] T014 [P2] [US3] CommandPalette : filtrer les entrées `requiresTenant` non débloquées (`CommandPalette.vue:122-128`).
- [ ] T015 [P3] [US3] `document.title` : résoudre `meta.title` i18n dans le guard (`router/index.js:317,335,403`).
- [ ] T016 [P2] [US3] FleetView : bannière d'erreur sur échec `/v1/admin/fleet/alerts` (`FleetView.vue:180`).

## Mobile / Kiosk / Edge / CI

- [ ] T017 [P1] [US4] `.github/workflows/mobile-distribute-main.yml:106,180,195` : ajouter `matrix.app.name == 'hr' && secrets.FIREBASE_APP_ID ||` au ternaire (parité mobile-distribute.yml).
- [ ] T018 [P2] [US4] `edge/install.sh` : télécharger/vérifier `Caddyfile.edge` (monté par docker-compose.yml:97) ; corriger les build contexts.
- [ ] T019 [P2] [US4] `branch-protection-guard.yml` : permissions admin ou retrait du check 403 systématique.
- [ ] T020 [P3] [US4] Dé-dupliquer les builds mobiles staging (mobile-distribute.yml + mobile-distribute-main.yml sur push main).
- [ ] T021 [P3] [US4] Kiosk : kiosk.db en 0600 à la création (bridge.py).
- [ ] T022 [P3] [US4] Bridge : borne body `_read_json` + rate-limit local `/local/punch` (`bridge.py:537-541`).
- [ ] T023 [P2] [US4] Manager : retirer les 11 routes GoRoute dupliquées de `front/mobile_apps/leopardo_manager/lib/app.dart`.

## Clôture

- [ ] T024 [P] [US1-US4] Vérifs finales : lint/tsc/jest/build vitrine + admin verts, actionlint sur les workflows, CHANGELOG ; merger les PRs vertes et supprimer les branches.

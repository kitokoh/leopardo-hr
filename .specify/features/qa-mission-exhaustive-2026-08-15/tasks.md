# Tasks: Mission QA Exhaustive 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

**Anti-duplication (protocole #2400)** : les fixes déjà en vol sur des branches NE SONT PAS dupliqués ici —
SN/GA (fix/2590-css-rate-test, fix/2551-ci-notice-working-days, fix/2580-ricf-tests), PHPStan modules
(fix/2587-phpstan-modules), correlation_id (fix/2583-correlation-guard), CORS pages.dev (#2333, déjà dans main — à déployer).

## Phase 1 — Backend : résilience login + onboarding (US1/US5)

- [x] T001 [P1] US1 `AuthService::login()` : encapsuler la résolution tenant/schéma (`setTenantSearchPath`, requêtes lookup, `resolveCompanyForEmployee`) dans un try/catch ciblé → `InvalidCredentialsException` (401) explicite si schéma/tenant absent ou invalide ; jamais de 500. Test `AuthServiceTest::test_login_with_orphaned_schema_returns_invalid_credentials` (fixture `user_lookups` → schéma inexistant).
- [x] T002 [P1] US1 Parcours `POST /api/v1/trial/verify` : reproduire le 500 en local (VerifyTrialSignup), corriger l'exception non gérée, retourner une erreur structurée (`success:false`, `error`, `message`) ; extension test `TrialProvisioningStatusTest`.

## Phase 2 — Web : régression blog/sitemap (US2) — ré-applique #2276 perdu au merge hybride #2469

- [x] T003 [P1] US2 `front/web/src/app/sitemap.ts` : ne publier `/blog/*` que si `enableBlog` (variable relue mais inutilisée — régression de c4a7a1bf/#2276 par d297ba7f/#2496).
- [x] T004 [P1] US2 `front/web/src/modules/vitrine/components/sections/MarketingReadinessSection.tsx` : gater les 4 liens « Explorer le blog » (fr/en/tr/ar) sur `getEnvConfig().enableBlog` (même logique Navbar/Footer).
- [ ] T005 [P2] US2 Activer `NEXT_PUBLIC_ENABLE_BLOG=true` en prod Vercel (le contenu existe) — action env documentée dans l'issue ; comportement sitemap/liens cohérent quel que soit le flag.

## Phase 3 — Web : funnel pricing honnête (US3)

- [x] T006 [P1] US3 `front/web/src/modules/vitrine/components/PricingSection.tsx` : `getPlanCtaHref` — Free→`free`, Pilot→`pilot`, Operations→`operations`, Enterprise→`enterprise` (fallback `/contact?type=enterprise`) ; plus aucun CTA « gratuit » vers un plan payant.
- [x] T007 [P1] US3 `front/web/src/app/(landing)/checkout/page.tsx` : dédupliquer `PLAN_CONFIG` (`starter`/`business` → alias `pilot`/`operations`), label Enterprise = « Enterprise » (pas « Scale »).
- [x] T008 [P2] US3 `front/web/src/app/(landing)/pricing/page.tsx` + `src/modules/vitrine/data/pricing.ts` : CTA/noms harmonisés, meta description à jour, FAQ « 14 jours » → « 30 jours ».

## Phase 4 — Admin : polish login (US7)

- [x] T009 [P3] US7 `front/admin-dashboard/src/views/auth/LoginView.vue` : accents (« Mot de passe oublié ? », « Accès Démo »).

## Phase 5 — Kiosk : état non configuré (US7)

- [x] T010 [P3] US7 `front/zkteco-kiosk/app.js` : `apiBaseUrl`/`deviceCode` manquants → état « Borne non configurée (config.json requis) » au lieu de « Error 404 ».

## Phase 6 — Qualité : tests cassés réparés (US6, hors périmètre branches en vol)

- [x] T011 [P1] US6 `tests/Unit/AbstractCountryRulesCapTest.php` : ajouter `use Tests\Support\SnPayrollFixtures;` (classe `tests/Support/SnPayrollFixtures.php`, namespace `Tests\Support`).
- [x] T012 [P1] US6 `tests/Unit/PayrollCalculatorUnitTest.php` : ajouter `use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;` (test ligne 67).
- [x] T013 [P1] US6 `tests/Unit/Modules/NotificationTest.php` : instancier `NotificationDispatcher` avec son argument requis (constructeur `__construct(1 arg)`).
- [x] T014 [P2] US6 Note de convergence : après merge des branches fix/2590 + fix/2551, re-vérifier `SenegalRulesUnitTest`, `PayrollCountryRulesTest`, `CemacRulesUnitTest` (pas de doublon).

## Phase 7 — Contrat API : drift OpenAPI (US4)

- [x] T015 [P2] US4 Ajouter les blocs OpenAPI pour les routes critiques manquantes (priorité : `/trial/signup|verify|status`, `/health/live|ready`, `/user/*`, `/payrolls*`, `/platform/auth/*`, `/platform/companies/*`, `/edge/health`, `/metrics`).
- [x] T016 [P2] US4 Garde CI/script : `scripts/route_openapi_compare.py` exécuté sur les routes modifiées (ou documenter le drift restant dans l'issue).

## Convergence

- [x] T017 Mettre à jour `CHANGELOG.md` (entrée `## [Unreleased]`), `.specify/memory/project-state.md`, cocher les tâches après merge, vérifier la prod post-déploiement (CORS check).

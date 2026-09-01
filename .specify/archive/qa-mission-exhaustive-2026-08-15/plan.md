# Implementation Plan: Mission QA Exhaustive 2026-08-15

**Branch**: `qa-mission-exhaustive-2026-08-15` | **Date**: 2026-08-15 | **Spec**: `.specify/features/qa-mission-exhaustive-2026-08-15/spec.md`

## Summary

Rétablir l'utilisabilité de la prod (CORS admin, login 500, onboarding trial), corriger la cohérence vitrine (blog/sitemap, funnel pricing), assainir le contrat OpenAPI, réparer les tests cassés de `main`, et améliorer les états d'erreur mobiles/kiosk. Les manquements sont documentés en issues GitHub (incidents) puis implémentés en fin de mission.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 12), TypeScript (Next.js 16), Vue 3 (Vite), JS vanilla (kiosk)

**Primary Dependencies**: Laravel (Sanctum, PostgreSQL multi-tenant search_path), Next.js, Vue 3 + Pinia, dio/Flutter (audit statique)

**Storage**: PostgreSQL (public + shared_tenants)

**Testing**: PHPUnit/Pest (api), jest (web), ESLint + Vite build (admin)

**Target Platform**: Prod Render API + Vercel vitrine + Cloudflare Pages admin

## Approach per User Story

### US1 — Prod live (CORS, login 500, trial 500, queue sync, super-admin)
- **CORS (F2)**: fix déjà dans main (#2333). Action = vérification post-deploy : script `scripts/check-deployed-cors.sh` (curl OPTIONS depuis origin pages.dev + assert `access-control-allow-origin`) exécutable en CI/manuel ; documenter le besoin de redéploiement dans l'issue.
- **Login 500 (F1/F5)**: `AuthService::login()` — encadrer les erreurs de résolution de schéma tenant (`setTenantSearchPath` + requêtes) : catch `QueryException`/`Schema` → `InvalidCredentialsException` (message explicite) au lieu du 500. Test de régression : `user_lookups` vers schéma inexistant → 401.
- **Trial verify 500 (F4)**: reproduire localement le parcours `VerifyTrialSignup` → identifier le point de 500 (probablement création tenant dans un état dégradé ou dépendance queue sync) → catch + message structuré.
- **Queue sync / super-admin absent (F3/F18)**: documenter dans l'issue ops (config prod), pas de changement de code.

### US2 — Blog (F6)
- `src/app/sitemap.ts` : retirer les URLs blog quand `enableBlog=false` (la variable est déjà lue).
- `src/modules/vitrine/components/sections/MarketingReadinessSection.tsx` : gater le lien « Explorer le blog » sur le même flag (comme Navbar/Footer).
- Vercel prod : activer `NEXT_PUBLIC_ENABLE_BLOG=true` (le contenu existe : 10+ articles + sitemap les référence) — action env documentée dans l'issue.

### US3 — Pricing (F7/F8)
- `PricingSection.tsx` `getPlanCtaHref` : mapper par plan réel — Free→`free`, Pilot→`pilot`, Operations→`operations`, Enterprise→`enterprise` (fallback contact).
- `checkout/page.tsx` `PLAN_CONFIG` : dédupliquer (`starter`/`business` supprimés ou aliasés), label Enterprise cohérent.
- `pricing/page.tsx` : harmoniser le mapping des CTA.
- Meta description pricing + FAQ durée d'essai (14→30 jours) alignées.
- Garde test jest sur les CTA (plan Free → plan=free).

### US4 — OpenAPI (F10)
- Ajouter les blocs OpenAPI pour les routes critiques manquantes (trial/*, health/live|ready, payrolls, user/*, platform auth/companies, edge health, reports clés).
- Ajouter un guard CI : `scripts/route_openapi_compare.py` en check (drift bloquant sur routes modifiées).
- Scope: prioriser les routes publiques/mobiles/onboarding ; le reste est tracé dans l'issue.

### US5 — Login résilient (inclus dans US1 backend)

### US6 — Qualité (F11/F12/F13)
- Réparer les 3 classes de tests cassées :
  - `tests/Unit/AbstractCountryRulesCapTest.php` : ajouter `use Tests\Support\SnPayrollFixtures;`
  - `tests/Unit/PayrollCountryRulesTest.php` : attendus SN via `SnPayrollFixtures::socialCharges(1000)` (calcul à la main, aligné #2473)
  - `tests/Unit/Modules/NotificationTest.php` : passer le(s) argument(s) requis au constructeur `NotificationDispatcher`
- Dette Pint (686 fichiers) et warnings PHPUnit 12 : documentés (issues), PAS de reformatage massif dans cette PR (bruit).

### US7 — Mobile/kiosk (F14/F15)
- Kiosk `app.js` : si `apiBaseUrl`/`deviceCode` absents → état « Borne non configurée — créer config.json » au lieu d'erreur réseau brute.
- Mobile : documenter la dualité d'auth (user_auth legacy vs auth canonique) — action de produit, pas de refactor dans cette vague.

## Dependencies
- US5/US1 backend fixes → tests backend verts.
- US2/US3 web → build + tests jest verts.
- US4 openapi → script de comparaison vert.

## Testing Strategy
- Backend : `php artisan test --filter="AuthServiceTest|SnPayrollFixtures|NotificationTest|TrialProvisioning"` + suite complète.
- Web : `npm test` (jest) + `npm run build` + smoke curl CTA.
- Admin : `npm run build` + lint.
- Kiosk : smoke manuel (chargement sans config → état explicite).

# Implementation Plan: QA Full Platform — 2026-08-15

**Branch**: `qa-full-platform-2026-08-15` | **Date**: 2026-08-15 | **Spec**: `.specify/features/qa-full-platform-2026-08-15/spec.md`

**Input**: Campaigne de test complète (smoke live + audit statique 5 surfaces) — issues #2652–#2662.

## Summary

11 manquements détectés lors de la campagne de test (P0→P2), chacun tracé par une issue GitHub et implémenté en fin de campagne. Les correctifs se répartissent en 6 PRs thématiques : backend (login/contrat d'erreur/routes), web (URLs mortes/SEO/i18n), admin (composants/e2e/base URL), mobile (mojibake/intégration marketing), openapi (documentation/garde CI).

## Technical Context

**Language/Version**: PHP 8.4 (API Laravel 12) · TypeScript/Next.js 16 (web) · Vue 3/Vite (admin) · Dart 3/Flutter (mobile)

**Primary Dependencies**: Laravel Sanctum, PostgreSQL (multi-tenant search_path), Sentry, Next.js, Vue 3 Pinia, Flutter melos

**Storage**: PostgreSQL 16 (schémas par tenant) · Redis

**Testing**: PHPUnit (backend, golden tests) · Playwright (web/admin e2e) · flutter_test (mobile) · garde OpenAPI (python)

**Target Platform**: Render (API) · Vercel (web) · Cloudflare Pages (admin) · stores mobiles (5 apps)

**Performance Goals**: aucun impact perf ; login inchangé en hot path ; i18n catalog inchangé hors garde

**Constraints**: Constitution — PHPStan strict level 8, Pint, coverage ≥ 65 %, tests avant implémentation pour la logique métier ; zéro secret dans le code ; PR avec `Closes #<issue>` ; CHANGELOG par PR ; jamais push sur main.

**Scale/Scope**: 11 issues · 6 PRs · backend (5 fichiers PHP + tests) · web (6 fichiers TS + sw.js + e2e) · admin (5 fichiers Vue/JS + e2e) · mobile (10 fichiers Dart + 3 yaml/ps1) · openapi (spec + garde)

## Constitution Check

*GATE: Must pass.* Toutes les PRs respectent : spec-first (ce dossier), auto-assignation, marker branch anti-doublon, DDD pour l'API, tests de régression cross-tenant pour tout endpoint sensible, PHPStan strict vert avant merge, CHANGELOG à jour.

## Project Structure

### Documentation (this feature)

```text
.specify/features/qa-full-platform-2026-08-15/
├── spec.md              # This campaign spec (user stories US1-US11)
├── plan.md              # This file
└── tasks.md             # Phase 2 output — task list per user story
```

### Source Code (repository root)

```text
api/
├── app/Core/Auth/Infrastructure/Services/AuthService.php      # US1
├── bootstrap/app.php                                           # US2 (renderers)
├── app/Modules/EdgeSync/Interfaces/Api/V1/Controllers/EdgeNodeController.php  # US3
├── app/Modules/EdgeSync/routes/api.php                         # US3
├── api/routes/modules/hr_extended.php                          # US3 (doublon)
├── app/Modules/Platform/.../TranslationCatalogController.php   # US3
├── app/Support/I18nCatalog.php                                 # US3
├── openapi.yaml                                                # US11
└── tests/                                                      # US1-US3, US11
front/web/src/app/(dashboard)/dashboard/page.tsx                # US4
front/web/public/sw.js                                          # US4
front/web/e2e/dashboard-quick-actions.spec.ts                   # US4
front/web/src/app/{layout,robots,sitemap}.ts                    # US5
front/web/src/modules/vitrine/lib/seo.ts                        # US5
front/web/vercel.json                                           # US6
front/admin-dashboard/src/services/api.js                       # US8
front/admin-dashboard/src/components/users/EditUserModal.vue    # US7 (supprimé)
front/admin-dashboard/src/views/edge/EdgeNodesView.vue          # US7
front/admin-dashboard/e2e/*.spec.js                             # US7
front/mobile_apps/{employee,manager}/lib/...                    # US9 (mojibake)
front/mobile_apps/melos.yaml, scripts, CI yml, pubspecs         # US10
```

**Structure Decision**: modifications localisées par surface ; pas de nouveau module ; backend strictement DDD existant.

## Complexity Tracking

> Aucune violation de Constitution. Les 6 PRs sont indépendantes (fichiers disjoints).

## Phase 0 — Research (déjà fait)

Constats complets dans les issues #2652–#2662 + spec.md. Points clés :
- Login 500 : chemin tenant-schema de `AuthService::login()` (lookup → search_path → requête Employee) ; état de données prod inconnu → durcissement défensif + shape.
- i18n catalog 500 : probable retard de déploiement (image sans `shared/i18n`), + garde manquante.
- SEO : `SITE_URL` dupliqué 8× avec fallback tiers/localhost.
- Admin : 20 composants orphelins dont 1 mock (`EditUserModal`).
- Mobile : mojibake sur 8 fichiers ; marketing orphelin.
- OpenAPI : 159 routes allowlistées ; garde CI ne voit pas les routes DDD.

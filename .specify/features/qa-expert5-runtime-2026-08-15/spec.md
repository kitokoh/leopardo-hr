# Feature Specification: QA Expert #5 — Test runtime complet + implémentation (2026-08-15)

**Feature**: `qa-expert5-runtime-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + tests runtime réels (serveurs locaux vitrine/admin/API) + revue statique.

## Contexte

Mission propriétaire : tester la suite Leopardo RH « dans tous les sens » (vitrine, web, admin, mobiles, workflows, API, logiques, onboarding, cohérence), consigner chaque manquement selon la méthode Spec Kit (spec → plan → tasks → issues), puis implémenter les correctifs et merger le maximum de branches — **main doit rester vert**.

## Plan de test (surfaces)

1. Vitrine `front/web` (build local green : lint/tsc/mojibake/build OK) — test runtime navigateur.
2. Admin `front/admin-dashboard` (build/lint OK) — test runtime navigateur.
3. API Laravel — composer install en cours, puis migrate + suites de tests + PHPStan strict.
4. Mobiles — cross-check statique routes Dart vs `route:list`/OpenAPI.
5. Workflows — `dev-hub/tools/validate-launch-workflows.ps1` (contrats) + i18n debt.
6. Onboarding — parcours trial/signup/demo + cohérence 14j/30j.
7. Cohérence — plans/pricing/i18n/canonicals/SEO.

## Findings non couverts (issues créées)

_(à remplir au fil du test — chaque constat = issue avec preuve + critères d'acceptation)_

## Constats de test (runtime + statique) — session en cours

### Vitrine `front/web` (build/lint/tsc/mojibake locaux verts)
1. **#3295 [P3] SignupForm — placeholder « Choisir » en dur** (select employés, toutes locales) → PR #3299 (Closes #3295).
2. **PWA sw.js précache routes authentifiées** (/dashboard, /attendance, /absences, /employees) → déjà suivi #3029/#2983 (PRs #3206/#3212/#3221 en cours chez d'autres agents — ne pas dupliquer).
3. **Sync tags PWA morts** : client enregistre sync-forms/sync-analytics, SW écoute leopardo-sync → suivi #3028/#2983 (mêmes PRs).
4. **Pas de `<link rel="alternate" hreflang>` dans le HTML des pages** alors que sitemap.xml les déclare (cohérence SEO) — NON couvert par une issue existante → à tracer.
5. **Domaine canonical www vs non-www** (www.leopardo-rh.com dans canonical, leopardo-rh.com dans sitemap) → corrigé par PR #3193 (source unique site-url.ts) — ne pas dupliquer.
6. Plans fantômes « Starter/Business » dans /download FAQ + « Starter/Pro » dans /branding → déjà suivis #2984/#2978.
7. `/blog` 404 (feature gate NEXT_PUBLIC_ENABLE_BLOG) → suivi #2906 (ops).

### Admin `front/admin-dashboard` (build/lint locaux verts)
8. **#3296 [P2] LoginView — accès démo supprimé par #2918** alors qu'AGENTS.md v4.16.250 + launch-workflow-contracts.json l'exigent (cf. #2646) → PR #3307 (Closes #3296).

### API Laravel (env local complet : PHP 8.4 + PostgreSQL + Redis)
9. Tooling : `check-issues-left-open-by-merged-prs.sh` plante (TypeError set non sérialisable, Python 3.10) → à tracer (P3 tooling).
10. 17 issues référencées par PRs mergées mais restées ouvertes (garde #2512) → **12 fermées avec preuve code** (voir journal).
11. Suite de tests complète : lancement en cours (ressources sandbox limitées — relance par lots).

---
description: "Tasks — QA Expert 5 session 2026-08-15"
---

# Tasks: QA Expert 5 — Session 2026-08-15

## Format: `[ID] [P] Story Description`

## US1 — Fiabiliser les tests backend
- [T1] [P] US1 Corriger `api/tests/Unit/Cameras/TestRtspSsidGuardTest.php` (203.0.113.10 → privateTargets, cible publique → IP 93.184.216.34) — **FAIT** (issue #3324, PR #3344)
- [T2] [P] US1 Vérifier localement `php artisan test --testsuite=Unit` + Pint sur la branche — **FAIT**
- [T3] [ ] US1 Lancer la suite Feature complète locale (validation main) — en cours

## US2 — Tester les surfaces
- [T4] [P] US2 Test live API Render (health, trial/signup, trial/status, api-explorer) — **FAIT** (L-01..L-05)
- [T5] [P] US2 Audits statiques 4 surfaces (web/admin/mobile/api) — **FAIT** (rapports `docs/qa/audit-expert5-2026-08-15/`)
- [T6] [P] US2 Corriger `front/web/src/app/(landing)/contact/page.tsx:47` placeholder FR — **FAIT** (issue #3352, PR #3357)

## US3 — Campagne de merge
- [T7] [ ] US3 Merger les PRs vertes (5 checks requis success) au fil de la CI
- [T8] [ ] US3 Merges de main dans les branches stale (ex: fix/2789-admin-supported-countries)
- [T9] [ ] US3 Vérifier `main` vert en fin de session (checks requis + suite locale)

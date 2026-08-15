# Tasks: QA Expert #5 — 2026-08-15

**Input**: spec.md + plan.md — chaque tâche correspond à une issue GitHub (label `qa-expert5-2026-08-15`).

## Phase 1 — API sécurité (payroll gates) 🎯
- [ ] T001 [P] US1 Routes `/payrolls/{id}` PUT/PATCH/DELETE + `/payrolls/{id}/validate` → middleware `api.manager:principal,comptable` (rh.php)
- [ ] T002 [P] US1 Test régression : manager dept → 403 sur update/validate/destroy ; principal → OK
- [ ] T003 US1 CHANGELOG + PR `Closes #N`

## Phase 2 — API honnêteté cockpit + erreurs correction
- [ ] T004 US2 `LaunchReadinessController` — `communication_governance` = 0 si `activeEmployees == 0`
- [ ] T005 US3 `AttendanceController@requestCorrection` — erreur « heure future » sur le champ fautif
- [ ] T006 US2/US3 CHANGELOG + PR `Closes #N`

## Phase 3 — Admin santé entreprise + labels pays
- [ ] T007 US4 `PlatformCompanyHealthService` — émettre `slug` + `created_at` (bloc company)
- [ ] T008 US5 Locales admin ×4 — clés pays manquantes (CG/CF/TD/GQ/NE/BJ/TG…)
- [ ] T009 US4/US5 CHANGELOG + PR `Closes #N`

## Phase 4 — Mobile devise + l10n stale
- [ ] T010 US6 `company_create_screen.dart` — devise depuis country-defaults (pas DZD en dur)
- [ ] T011 US6 5 modèles partagés — fallback devise aligné T086 (DZD dernier recours)
- [ ] T012 US6 `generate: true` retiré ou `l10n.yaml` ajouté (4 apps)
- [ ] T013 US6 CHANGELOG + PR `Closes #N` (checks mobile CI)

## Phase 5 — Docs + web
- [ ] T014 US7 AGENTS.md + README — cartographie apps corrigée (employee présent, kiosk web)
- [ ] T015 US8 `sitemap.ts` — entrée `/blog` gatée sur `NEXT_PUBLIC_ENABLE_BLOG`
- [ ] T016 US7/US8 CHANGELOG + PR `Closes #N`

## Phase 6 — Merge & validation finale
- [ ] T017 Pousser `docs/qa/QA_SESSION_2026-08-15-expert5.md` (registre complet)
- [ ] T018 PRs vertes mergées ; main vert confirmé via actions GitHub

## Dependencies & Execution Order
- T001-T003 indépendants (fichier routes) — Phase 1 d'abord (sécurité)
- T004-T006 indépendants (2 contrôleurs distincts)
- T007-T009 indépendants (service + locales)
- T010-T013 vérifiés par CI mobile (flutter analyze)
- T014-T016 docs/web, vérifiés par build local
- T017-T018 à la fin

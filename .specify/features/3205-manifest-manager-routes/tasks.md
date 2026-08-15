# Tasks: Régression manifeste mobile — routes manager restaurées (issue #3205)

**Input**: spec.md + plan.md — 1 régression P1.

## Phase 1 — Restaurer les routes
- [ ] T001 [#3205] Créer branche `fix/3205-manifest-manager-routes` depuis origin/main
- [ ] T002 [#3205] Réinsérer les 11 GoRoutes (attendance, absences, salary-advances, payrolls, evaluations, notifications, history, me/monthly, modules, team, tasks) dans la ShellRoute de `front/mobile_apps/leopardo_manager/lib/app.dart`
- [ ] T003 [#3205] Vérifier : aucune déclaration `/modules/rh`, `/ai-chat`, `/vehicle-map` ; pas de doublon `/cabinet/:folderId`
- [ ] T004 [#3205] Vérifier : `bash dev-hub/tools/check-mobile-manifest-routes.sh` → OK

## Phase 2 — CHANGELOG + PR
- [ ] T005 [#3205] Entrée CHANGELOG `### Fixed` sous `[Unreleased]`
- [ ] T006 [#3205] Commit + push + PR `Closes #3205` ; checks CI verts (Mobile Apps CI)

## Phase 3 — Post-merge
- [ ] T007 [#3205] Vérifier garde verte sur main après merge

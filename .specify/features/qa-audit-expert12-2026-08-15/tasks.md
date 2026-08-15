# Tasks: Audit 360° expert 12 — 2026-08-15

**Input**: spec.md (`.specify/features/qa-audit-expert12-2026-08-15/`)

**Tests**: `npm run lint` admin 0 warning ; `npm run build` admin vert ; `tsc --noEmit` + `eslint --max-warnings 0` web verts.

## Phase 1 — Registre de session & spec (US3, P2) 🎯

- [ ] T001 [P] Créer la branche `docs/qa-audit-expert12-2026-08-15` depuis `origin/main` avec les artefacts Spec Kit (spec.md, tasks.md, findings-registry.md, plan.md) + `docs/qa/QA_SESSION_2026-08-15-expert12.md` (registre live réconcilié : API v4.23.5 + queue sync → #2812/#3562 ; admin Pages 200 ; vitrine 404/NXDOMAIN → #3452 ; FCM placeholders → #3152 ; OpenAPI drift 0 ; lint/build verts) et ouvrir la PR docs (Closes issue registre).

## Phase 2 — Lint admin 0 warning (US1, P3)

- [ ] T002 [P] `CommandPalette.vue` : retirer les 5 imports inutilisés (ArrowDownTrayIcon, CalendarDaysIcon, BriefcaseIcon, AcademicCapIcon, ChatBubbleLeftRightIcon).
- [ ] T003 [P] `SystemView.vue:84` : retirer l'import InformationCircleIcon (introduit par #3699).
- [ ] T004 [P] `WebhooksView.vue:130` : retirer l'import StatusBadge (introduit par #3701).
- [ ] T005 [P] `EdgeNodesView.vue:220` : supprimer le helper formatDuration mort.
- [ ] T006 [P] `TaxRatesView.vue:352` : supprimer le helper formatDate mort.
- [ ] T007 [P] Vérifier `npm run lint` (0 warning) + `npm run build` (vert) + CHANGELOG ; PR `fix/<issue>-admin-lint-warnings` avec `Closes #<issue>`.

## Phase 3 — Vérifications finales (US2/US4, P2)

- [ ] T008 [P] Vérifier les checks GitHub Actions des PRs docs + fix, merger les PRs vertes (`gh pr merge --merge --delete-branch`), supprimer les branches supersédées restantes.
- [ ] T009 [P] Mettre à jour `AGENTS.md`/CHANGELOG si une leçon opérationnelle émerge (ex. validation locale des gardes vitrine pendant la famine CI).

## Dependencies & Execution Order

- T001 indépendant (docs) — parallélisable.
- T002-T006 indépendants (fichiers Vue distincts) — parallélisables.
- T007 après T002-T006 (validation globale).
- T008 après merges des PRs.

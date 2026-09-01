# Tasks: QA — Session fusion & test 2026-08-15

**Input**: spec.md + findings-registry.md — chaque tâche mappe un constat (F#) et/ou une issue.

## Phase 1 — F1 [P1] Trial/verify 503 (processing constraint)
- [x] T001 Migration `2026_08_15_000005_add_processing_to_company_requests_status` (idempotente, qualifiée)
- [x] T002 Vérif : `SelfServiceTrialTest` 7/7 verts (52 assertions) sur PG16
- [x] T003 PR #3227 ouverte + rebasée sur main

## Phase 2 — F2 [P2] Email de bienvenue : durée réelle
- [x] T004 `TrialWelcomeMail::resolveTrialDays()` + propriété publique `$trialDays`
- [x] T005 Test : `$mail->trialDays === 14`
- [x] T006 PR #3229 ouverte + rebasée

## Phase 3 — F3 [P1] Build admin
- [x] T007 `CommandPalette.vue` : DocumentReportIcon → DocumentTextIcon
- [x] T008 PR #3161 (mergée par session parallèle, fix présent sur main)

## Phase 4 — Constats ouverts à implémenter (selon capacité)
- [ ] T009 [F4] OpenAPI : documenter les routes manquantes par groupe (PA2/plateforme d'abord)
- [ ] T010 [F5] Mobile : aligner manifeste et routeurs réels (#2212)
- [ ] T011 [F6] Vérifier les drips emails (durée annoncée vs provisionnée)

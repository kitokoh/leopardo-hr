# Tasks: QA Expert 2026-08-15

**Input**: spec.md + plan.md
**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — F1 Médias LFS vitrine (P1) — issue à créer

- [ ] T001 [P1] [F1] `git lfs untrack` 5 fichiers front/web/public + override `.gitattributes` (`-filter`) + `git add --renormalize` → binaires réels commités
- [ ] T002 [P1] [F1] Vérification : `git lfs ls-files` sans ces fichiers, `file` = PNG/MP4/WebM, `curl` prod → signature binaire après re-deploy

## Phase 2 — F2 Tests backend rouges (P1) — issue à créer

- [ ] T003 [P1] [F2] `SnPayrollFixtures::socialCharges()` — 9 valeurs employer alignées moteur (CSS famille 7 %, plafond 63 000) + commentaires
- [ ] T004 [P1] [F2] `GoldenSnPayrollTest` — lignes 51/111/139 (11426.60 / 54288 / 91776)
- [ ] T005 [P1] [F2] `PayrollCountryRulesTest` — SN employer 154.0 → 194.0
- [ ] T006 [P1] [F2] `PayrollCalculatorUnitTest` — restaurer `use UnsupportedCountryRulesException`
- [ ] T007 [P1] [F2] `CedeaoRulesUnitTest` — TG confidenceLevel placeholder → pilot
- [ ] T008 [P1] [F2] `CemacRulesUnitTest` — GA noticePeriodDays 30.0 → 22.0
- [ ] T009 [P1] [F2] `NotificationTest` — NotificationDispatcher instancié avec son argument
- [ ] T010 [P1] [F2] Vérification : 7 suites ciblées → 0 échec ; Pint + PHPStan strict sur fichiers touchés

## Phase 3 — F3 PayrollTenantIsolation 403→404 (P2) — issue à créer

- [ ] T011 [P2] [F3] `test_cross_tenant_tax_slab_is_inaccessible` — assertForbidden → assertNotFound (PUT + DELETE) + commentaire Constitution §II
- [ ] T012 [P2] [F3] Vérification : `PayrollTenantIsolationTest` → 8/8 verts

## Phase 4 — F4 Ancres /docs (P3) — issue à créer

- [ ] T013 [P3] [F4] Ajouter/corriger ids (api, webhooks-events, webhooks-intro, webhooks-testing) + lier ids orphelins (mobile-install, security, webhooks-security)
- [ ] T014 [P3] [F4] Vérification script hrefs ⊆ ids ; lint web vert

## Phase 5 — F5 Mobile dio.options (P3) — issue à créer

- [ ] T015 [P3] [F5] Header Accept-Language par requête (requestWithRetry) dans 3 user_auth_repository.dart
- [ ] T016 [P3] [F5] Vérification : `rg "apiClient\.dio\."` → uniquement dio.download

## Finalisation

- [ ] T017 CHANGELOG.md — entrées `### Fixed` (une par PR)
- [ ] T018 PRs avec `Closes #N` dans le body + anti-doublon vérifié (branches/PRs avant push)

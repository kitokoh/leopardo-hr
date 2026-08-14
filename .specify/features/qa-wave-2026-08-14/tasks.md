# Tasks: QA Wave 2026-08-14 — Fiabilisation backend + UI admin

**Input**: spec.md + plan.md

## Phase 1 — Tests paie (issues #2288/#2289)

- [x] T001 [P1] **Bug impl** : `CedeaoPayrollRules::noticePeriodDays()` → CI_COMPLIANCE.md §8 (défaut 30/60, cadres 90, ouvriers 8/15)
- [x] T002 [P1] `Unit/Payroll/CedeaoRulesUnitTest` : ITS 2024 (20 000, CN = 0) + 6 tranches
- [x] T003 [P1] `Unit/CedeaoRulesUnitTest` : préavis 30/60/60 + catégories ; BF/ML pilot hors loop placeholder
- [x] T004 [P1] `Unit/AbstractCountryRulesCapTest` : CNSS CI patronal 79 554,18 (plafonds branche 70 000)
- [x] T005 [P1] `Unit/CemacRulesUnitTest` : GA pilot 8 tranches + CNSS plafonnée ; CF/TD/GQ placeholder
- [x] T006 [P1] `Unit/PayrollCalculatorUnitTest` : attend `UnsupportedCountryRulesException` (#1868)
- [x] T007 [P1] Golden CI SMIG : patronal 9 187,50 → 8 800,00 (plafonds CNPS 70 000)
- [x] T008 [P1] Golden CI préavis : ≥ 10 ans → 60 j (employé, doc §8)
- [x] T009 [P1] Golden SN T1 : patronal 66 528 → 51 768 (CSS plafonnée 63 000)
- [x] T010 [P1] `PayrollCalculationContractTest` CI : social_employer 61 250 → 27 925 · total_cost 527 925

## Phase 2 — Login admin (issue #2290)

- [x] T011 [P2] Retirer « Mot de passe oublié ? » (aucun flux self-service ; process ops documenté)
- [x] T012 [P2] Retirer « Sécurité » mort ; « Support » → `mailto:support@leopardo-rh.com`
- [x] T013 [P2] Lint + build admin-dashboard verts

## Phase 3 — Livraison & coordination

- [x] T014 [P1] Issues #2288/#2289/#2290 rédigées + auto-assignées ; convergence avec le fix PHPStan parallèle (0f1ea1ee)
- [x] T015 [P1] Feature spec kit `.specify/features/qa-wave-2026-08-14/`
- [x] T016 [P1] Harnais QA : `scripts/qa_api_smoke.py` (42 checks) + `scripts/qa_api_write_workflows.py` (12 checks)
- [ ] T017 [P1] Branches + PR (`Closes #N`), checks CI verts, merge + suppression branches
- [ ] T018 [P1] CHANGELOG.md + AGENTS.md (leçons QA)
- [ ] T019 [P2] Rapport final de campagne QA (constats + correctifs + note ops prod 4.23.5)

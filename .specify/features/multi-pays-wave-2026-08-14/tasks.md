# Tasks: Vague Multi-Pays Paie Afrique 2026-08 (complétion)

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — Moteur multi-pays (MERGÉ sur main)

- [x] T001 [P1] US1 Résolveur unique des règles pays sans fallback silencieux (#1868) — `CountryRulesResolver`
- [x] T002 [P1] US1 Classes de règles pays CEDEAO/CEMAC/DZ + `AbstractCountryRules` (défauts, complianceSource/verificationDate)
- [x] T003 [P1] US1 Golden tests par pays (DZ/CM/GA/CG/CI/SN/BF/ML) + `GoldenGenericEngineTest` (#1938) — cas sourcés à la main
- [x] T004 [P1] US2 Contrat de calcul enrichi du bloc `compliance` (#1872 backend) + clés i18n `payroll.compliance_warning_*` (4 locales)
- [x] T005 [P1] US2 Garde placeholder 422 + acceptation auditée (#1872)
- [x] T006 [P1] US1 Audit et observabilité des calculs (#1874) — `payroll_calculation_audits`
- [x] T007 [P1] US1 ITS CI 2024 (réforme art. 119 bis) + migration idempotente (#1918)
- [x] T008 [P1] US3 Registre `docs/payroll/VALIDATION_EXPERTE.md` + template + fiches pays (#1904/#1912)
- [x] T009 [P1] US4 Playbook onboarding pays + garde `check-country-catalog.sh` (#1875)
- [x] T010 [P1] US1 Garde anti-collision migrations (#1962) + merge queue (#2032)
- [x] T011 [P1] US1 Réparations main vert (PHPStan strict/modules, parseerror, tests) — vagues `fix/main-*` 2026-08-14

## Phase 2 — Conformité & validation experte

- [x] T012 [P1] US3 Ticket SN #1912 ouvert avec checklist expert + fiche SN_COMPLIANCE.md §12
- [x] T013 [P2] US3 Ticket reliquats validation experte #2124 (RICF CI, abattement GA, CNAC DZ, CSS famille SN)

## Phase 3 — Clients (frontends)

- [ ] T014 [P1] US2 Afficher le bloc `compliance` dans la Web App `front/web` (simulation + fiche de paie) — issue #2116
- [ ] T015 [P2] US2 Afficher le bloc `compliance` dans l'admin dashboard (`front/admin-dashboard`) et le mobile (suivi)

## Phase 4 — Complétion catalogue

- [ ] T016 [P2] US4 Onboarding TG placeholder→pilot (playbook #1875) — issue #2121
- [ ] T017 [P3] US1 Golden tests MA/TN (≥ 3 par pays, sourcés) — issue #2122
- [ ] T018 [P2] US1 Préavis SN par catégorie (8 j / 1 m / 3 m) — issue #2123
- [ ] T019 [P2] US3 RICF CI art. 120 / abattement DGI GA / CNAC DZ — réponse expert (issue #2124)
- [ ] T020 [P2] US1 Fiche validation SN signée → `confidenceLevel()` SN → `production` (issue #1912, bloqué externe)

## Phase 5: Convergence

- [ ] T021 Actualiser `.specify/constitution.md` §VIII (tableau Statut cible) — ajouter un renvoi vers `docs/payroll/VALIDATION_EXPERTE.md` pour le statut RÉEL des pays (DZ/FR `pilot` dans le code vs `production` cible au tableau ; la ligne SN doit rester `pilot` jusqu'à signature #1912) (contradicts: Constitution VIII vs code)
- [ ] T022 Miroiter la constitution réelle dans `.specify/memory/constitution.md` (actuellement template non rempli) — les runs `/speckit-converge` liront alors les principes MUST/SHOULD du projet au lieu de les ignorer (missing: Constitution I-VIII)
- [ ] T023 Corriger la section dupliquée « Procédure de mise à jour des taux » dans `docs/payroll/SN_COMPLIANCE.md` (présente deux fois à l'identique, fin de fichier) (unrequested) — **couvert par PR #2115** (restaure §12 + dé-doublonne)

## Phase 6: Convergence (2026-08-14 — agent coordinateur, /speckit-converge)

**Statut Phase 5 vérifié sur main** : T021/T022 mergés (PR #2139 — issues #2137/#2138) ; T023 mergé (PR #2115) ; T015 volet admin mergé (PR #2135 — issue #2112) ; T016/T018 mergés (PRs #2121/#2136) ; T019 partiel mergé (PR #2133 — abattement GA, reste RICF CI/CNAC DZ expert #2124).

- [ ] T024 [P1] US2 Web App `front/web` — afficher le bloc `compliance` (niveau + avertissement + source + verification_date) sur la simulation et la fiche de paie — issue #2116 toujours ouverte, aucun PR
- [ ] T025 [P3] US2 Mobile (employee/manager) — afficher l'avertissement de conformité sur la fiche de paie — issue #2144 créée lors de ce converge
- [ ] T026 [P1] US1/§IV Main « complètement vert » — vérifier les 5 required checks (dont « Backend Coverage », désormais déclenché sur push main par 651ce079) sur le prochain push direct / merge
- [x] T027 [P2] US3 §VIII constitution — renvoi statut réel → `docs/payroll/VALIDATION_EXPERTE.md` (issue #2137 → PR #2139 mergée)
- [x] T028 [P2] US3 Miroir constitution réelle dans `.specify/memory/constitution.md` (issue #2138 → PR #2139 mergée)
- [x] T029 [P2] US2 Admin dashboard — bandeau conformité TaxSlabsView + clés `payroll.confidence.*` (issue #2112 → PR #2135 mergée)
- [x] T030 [P1] US1 PHPStan Strict main vert — 9 erreurs neutralisées (issue #2132 → PR #2134 mergée)
- [x] T031 [P1] US1 Golden BF overtime fix (issue #2114 → PR #2114 mergée)
- [ ] T032 [P3] Process — nettoyage des branches distantes obsolètes + scratch `tmp-agent-integ` (issue #2130, analyse postée)

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

- [x] T014 [P1] US2 Afficher le bloc `compliance` dans la Web App `front/web` (simulation + fiche de paie) — issue #2116 (PR #2157)
- [x] T015 [P2] US2 Afficher le bloc `compliance` dans l'admin dashboard (`front/admin-dashboard`) et le mobile (suivi) — admin #2135, mobile #2143 (PR #2218)

## Phase 4 — Complétion catalogue

- [x] T016 [P2] US4 Onboarding TG placeholder→pilot (playbook #1875) — issue #2121 (PR #2160)
- [x] T017 [P3] US1 Golden tests MA/TN/FR/TR/CA (≥ 3 par pays, sourcés) — issue #2119 (PR #2166) + abattement TN #2261 (PR #2300)
- [x] T018 [P2] US1 Préavis SN par catégorie (8 j / 1 m / 3 m) — issue #2123 (merged #2151)
- [x] T019 [P2] US3 RICF CI art. 120 implémenté (issue #2117, PR #2163) — CNAC DZ / réponse expert restent sur #2124
- [ ] T020 [P2] US1 Fiche validation SN signée → `confidenceLevel()` SN → `production` (issue #1912, bloqué externe)

## Phase 5: Convergence

- [ ] T021 Actualiser `.specify/constitution.md` §VIII (tableau Statut cible) — ajouter un renvoi vers `docs/payroll/VALIDATION_EXPERTE.md` pour le statut RÉEL des pays (DZ/FR `pilot` dans le code vs `production` cible au tableau ; la ligne SN doit rester `pilot` jusqu'à signature #1912) (contradicts: Constitution VIII vs code)
- [ ] T022 Miroiter la constitution réelle dans `.specify/memory/constitution.md` (actuellement template non rempli) — les runs `/speckit-converge` liront alors les principes MUST/SHOULD du projet au lieu de les ignorer (missing: Constitution I-VIII)
- [ ] T023 Corriger la section dupliquée « Procédure de mise à jour des taux » dans `docs/payroll/SN_COMPLIANCE.md` (présente deux fois à l'identique, fin de fichier) (unrequested) — **couvert par PR #2115** (restaure §12 + dé-doublonne)


## Phase 6 — Session 2026-08-14 (agent autonome) — PRs créées

- [x] T024 [P1] Sécurité : auth ZKTeco X-Device-Token (P0 #2216) — PR #2246
- [x] T025 [P1] Sécurité : RBAC Fleet & Planning manager-only (P1 #2217) — PR #2263
- [x] T026 [P1] Payroll : préavis fin de contrat = jours OUVRÉS 7 pays (P1 #2219) — PR #2280
- [x] T027 [P1] Payroll : parité simulateur ↔ bulletin (TRIMF SN, by_slab annualisé, assiettes T2/CSG) (P1 #2220) — PR #2291
- [x] T028 [P1] Payroll : exports financiers valides (SEPA sans placeholder, journal négatifs, accounting anti-injection) (P1 #2223) — PR #2294
- [x] T029 [P3] Payroll : déclaration CNSS/INPS BF/ML CSV (#2158) — PR #2192
- [x] T030 [P3] Payroll : déclaration CNSS GA/CG CSV (#2155) — PR #2194
- [x] T031 [P2] Compliance : abattement IRPP TN 10 % art. 39 (#2261) — PR #2300
- [x] T032 [P2] CI : actionlint push inconditionnel (check requis main) — PR #2142 (merged)

## Travail restant (converge 2026-08-14)

- [ ] T033 [P2] Maroc — abattement frais professionnels (issue #2260, Agent-Ready)
- [ ] T034 [P2] Calendriers fériés légaux officiels 9 pays (issue #2255)
- [ ] T035 [P2] SSO SAML/OIDC stub → 501 (issue #2251 / #2197)
- [ ] T036 [P1] Notifications in-app jamais déclenchées (issue #2200)
- [ ] T037 [P1] Export bancaire SEPA placeholder → données réelles (issue #2198, volet données)
- [ ] T038 [P1] UsersView/AnalyticsView admin en données mock → réelles (issues #2184/#2185/#2269)
- [ ] T039 [P2] Drift OpenAPI ↔ routes : 168 routes non documentées (issue #2254/#2267)
- [ ] T040 [P1] Mobile : routes absentes des routeurs (issue #2212)
- [ ] T041 [P2] Préavis CI palier ≥ 10 ans = 90 j calendaires à confirmer (issue #2264)
- [ ] T042 [P1] SN — validation experte → confidenceLevel production (issue #1912, bloqué externe)

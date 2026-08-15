# Tasks: Audit expert API — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

> Conversion en issues GitHub : label `qa-audit-2026-08-15`, méthode Spec Kit taskstoissues.
> **Sessions précédentes (canoniques, à ne pas dupliquer)** : série backend #2614-#2626 (T001-T013) — Stripe signature fail-closed, Chargily, email-bounce, register invitation-only, suspended login, OAuth Google state, magic link, trial/status limiter, growth/partner middleware, calendar OAuth scope, impersonation /admin, webhook test doublon, forgot-password. Mes tâches T006/T012 (doublons) ont été fermées en faveur de #2616/#2625.

## Phase 1 — P1 bloquants (US A1, A2)

- [x] T005 [P1] A1 `AbsenceService::approve()` : vérifier/déduire sur `LeaveBalance` (snapshot, `lockForUpdate`, scopé employee+company+absence_type) au lieu de la chaîne `LeaveBalanceLog` ; log d'audit conservé. Tests : crédit→approbation OK, solde insuffisant 422, race, isolation tenant. (issue #2666)
- [x] T006 [P1] A2 Webhook email-bounce fail-closed — **doublon fermé** : canonique #2616 (T003 backend). (issue #2667)

## Phase 2 — P2 données & contrat (US A3-A10)

- [x] T007 [P2] A3 `StripeWebhookController` : erreurs de traitement → 500 (retry Stripe) ; signature seule → 400/401. Test erreur simulée. (issue #2668)
- [x] T008 [P2] A4 Pointage : `lockForUpdate` session ouverte + index unique partiel `(employee_id, date, session_number)`. Tests parallèles check-in/check-out. (issue #2669)
- [x] T009 [P2] A5 `GET /employees/{employeeId}/leave-balances` : utiliser le paramètre de route (fallback query rétro-compat). Test scope employé + 404 cross-équipe. (issue #2670)
- [ ] T010 [P2] A6 `days_count` : convention jours ouvrés (calendrier entreprise) ou convention documentée par type. Tests vendredi→lundi. (issue #2671)
- [x] T011 [P2] A7 `PayrollCalculator::sumApprovedLeaveDays` : clipping sur `[period_start, period_end]`. Golden test absence chevauchante. (issue #2672)
- [x] T012 [P2] A8 Route webhook test dupliquée — **doublon fermé** : canonique #2625 (T012 backend). (issue #2673)
- [x] T013 [P2] A8 Notifications : canonicaliser les verbes (une paire PATCH `{id}/read` + POST `mark-all-read`), documenter. (issue #2674)
- [ ] T014 [P2] A8 OpenAPI : aligner noms de paramètres (webhookEndpoint, loanId, sessionId, employee, id) + documenter les groupes manquants prioritaires. (issue #2675)
- [x] T015 [P2] A9 `AbsenceService::create()` : garde de solde en transaction avec `lockForUpdate`. Test concurrence. (issue #2676)
- [x] T016 [P2] A9 `ExpenseClaim` : validation des transitions de statut. Tests draft→approved interdit, approved→rejected interdit. (issue #2677)
- [x] T017 [P2] A9 `RequestTrialSignup` : échec OTP/CompanyRequest → échec explicite (rethrow/transaction). Tests. (issue #2678)
- [x] T018 [P2] A10 `referenceGross12Months` : compter `calculated` + `validated`. Golden test indemnité 1/10e. (issue #2679)

## Phase 3 — P3 hygiène (US A11)

- [ ] T019 [P3] A11 `VerifyTrialSignup` : ne plus renvoyer `temp_password` (lien/token de reset). (issue #2680)
- [x] T020 [P3] A11 Labels `work_state_label` → `lang/*.php` (EmployeeController). (issue #2681)
- [x] T021 [P3] A11 Clamp `per_page` (1-100) sur leave-balances/accruals/payroll/expense. (issue #2682)
- [x] T022 [P3] A11 `lang/ar|tr/payroll.php` : ajouter les 2 clés manquantes. (issue #2683)
- [x] T023 [P3] A11 Un seul verbe par action approve/reject/submit (PUT). (issue #2684)
- [x] T024 [P3] A11 `computeOvertimePay` : pas d'arrondi avant multiplicateur (précision finale seule). Golden test. (issue #2685)
- [x] T025 [P3] A11 `NON_WORK_TYPES` étendu (`leave`, `holiday`, …) pour `hours_worked`/overtime. (issue #2686)
- [ ] T026 [P3] A11 `executeCalculateRun` : chunk/batch des agrégats (N+1). (issue #2687)
- [x] T027 [P3] A11 `MarketingLeadController` : log bruyant si secret non configuré (fail-open documenté). (issue #2688)
- [x] T028 [P3] A11 `KioskController` : `search_path` dans `try/finally` (reset). (issue #2689)
- [x] T029 [P3] A11 Middleware `/ai/*` : lier `current_company` (pas seulement l'attribut). (issue #2690)
- [x] T030 [P3] A11 URLs prod codées en dur (`config/cameras.php` wss, `config/demo.php` admin@) → env. (issue #2691)

## Convergence

- [ ] T031 Mettre à jour `.specify/memory/project-state.md`, `CHANGELOG.md`, `AGENTS.md` (leçons QA), cocher les tâches après merge.

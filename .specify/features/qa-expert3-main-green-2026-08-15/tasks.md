# Tasks: QA Expert #3 — main vert + essai self-service + cohérence (2026-08-15)

## Phase 1 — Main vert PHPStan Strict (192 → 0 erreurs)
- [x] T001 #3139 : 10 erreurs app/tests (Auth, CalendarConnection, EdgeNode, Kiosk, tests) — MERGED
- [x] T002 #3185 : erreurs app Payroll/Planning (PayrollCalculator, AbsenceService, AttendanceLog, AbsenceType) + baseline strict régénérée — MERGED
- [x] T003 Vérification : phpstan-strict.neon 0 erreur local (PHPStan 2.1.53 = lock CI) + ratchet PA2-ARCH-005 vert
- [x] T004 Post-#3185 : 14 nouvelles erreurs (vague merges) → GenerateBankExportJob (runtime bug), PayrollCalculator duplicate keys, GrowthModuleTest, drift comptes VehicleControllerTest/Camera → en cours (PR dédiée)

## Phase 2 — Essai self-service (test bout-en-bout base fraîche)
- [x] T005 #3210 (P1) : contrainte company_requests_status_check → migration 2026_08_15_000006 (5 statuts) — PR #3211
- [x] T006 #3057 : échec OTP surfacé 502 + i18n 4 locales + test — PR #3211
- [x] T007 Vérification : SelfServiceTrialTest 8/8 + EmailBounce 5/5 (PostgreSQL 16 frais)

## Phase 3 — Cohérence durée d'essai (canon 14 jours)
- [x] T008 #3056 : verify API days=30→14 — PR #3218
- [x] T009 SEO vitrine : 2 metas « 30 jours » + plans fantômes → 14 jours + plans réels — PR #3218
- [x] T010 Admin dashboard : badge signup 30→14 (fr/en/ar/tr) — PR #3218
- [x] T011 #3058 : webhook email-bounce configurable — PR #3215

## Phase 4 — Garde continue
- [ ] T012 Surveiller le merge des PRs #3211/#3215/#3218 (bot owner)
- [ ] T013 Vérifier main vert post-merge (PHPStan + MSV + parity)

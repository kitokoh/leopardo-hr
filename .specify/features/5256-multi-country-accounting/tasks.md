# Tasks: Moteur multi-pays — plan comptable par pays + écritures équilibrées (Closes #5256)

- [x] T1. Issue #5256 self-assignée + commentaire de claim + branche `mod/payroll/5256-multi-country-accounting` (claim marker)
- [x] T2. Spec `.specify/features/5256-multi-country-accounting/spec.md`
- [x] T3. `PayrollCountryChartOfAccounts` — registre 21 pays (10 explicites + dérivés), confidenceLevel + références
- [x] T4. `PayrollAccountingExportService::journalLines()` — écritures équilibrées (débit/crédit exclusifs, référence run)
- [x] T5. `PayrollAccountingExportJournalTest` — golden par pays + équilibre + exclusion non validés + déductions perso + pays dérivés
- [x] T6. `docs/payroll/MULTI_PAYS_PLAN_COMPTABLE.md` + CHANGELOG [Unreleased]
- [x] T7. Validation locale : tests + PHPStan strict + Pint
- [ ] T8. PR `Closes #5256` → CI verte → merge → suppression branche

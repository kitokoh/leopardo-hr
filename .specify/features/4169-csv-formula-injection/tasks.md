# Tasks: Export CSV — neutralisation injection de formules (Closes #4169)

**Spec**: `.specify/features/4169-csv-formula-injection/spec.md`

- [x] T1. Claim : issue #4169 self-assignée + branche `fix/4169-csv-formula-injection` (protocole #2400)
- [x] T2. `App\Support\CsvCellSanitizer` — helper partagé (préfixe `'` pour `=+-@\t\r` en tête de cellule non numérique)
- [x] T3. `ExportController::toCsv` — sanitize appliqué avant échappement structurel
- [x] T4. `AuditLogController` (export CSV) — sanitize des champs texte (user, action, auditable_type, valeurs JSON)
- [x] T5. `AttendanceMonthlyReportService::toCsv` — sanitize matricule + nom
- [x] T6. `PayrollAccountingExportService` — méthode privée #2223 remplacée par le helper partagé
- [x] T7. Tests : `CsvCellSanitizerTest` (5 scénarios) + régression export employés (`'=cmd|`)
- [ ] T8. Vérifs locales : PHPStan strict + Pint + tests Export/Audit/Attendance/Payroll
- [ ] T9. CHANGELOG (`### Fixed`) + `docs/specifications/ISSUE_4169.md`
- [ ] T10. PR `fix(api): export CSV — neutralisation injection de formules (OWASP) sur les 4 sites (Closes #4169)` → CI verte → merge → suppression branche

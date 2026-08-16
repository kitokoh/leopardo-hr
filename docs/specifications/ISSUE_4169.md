# ISSUE_4169 — Export CSV : neutralisation injection de formules (OWASP)

> Spec Kit : `.specify/features/4169-csv-formula-injection/spec.md` · Tâches : `tasks.md`
> Branche : `fix/4169-csv-formula-injection` · Issue : #4169

## Correctif

`App\Support\CsvCellSanitizer::neutralize(mixed): string` — helper partagé :

- chaîne commençant par `= + - @ \t \r` (non numérique) → préfixée `'` ;
- valeur numérique (y compris `-1234.5`) → intacte ;
- `null` → `''` ; déjà préfixée → inchangée.

Appliqué aux 4 sites d'export : `ExportController::toCsv`, `AuditLogController`,
`AttendanceMonthlyReportService::toCsv`, `PayrollAccountingExportService`
(méthode privée #2223 supprimée — plus de duplication).

## Tests

- `CsvCellSanitizerTest` : 5 scénarios (préfixes, texte, numériques, null/vide, non-double-prefixe).
- `ExportControllerTest::test_csv_export_neutralizes_formula_injection_in_employee_names` : un employé `=cmd|` est exporté `'=cmd|`.

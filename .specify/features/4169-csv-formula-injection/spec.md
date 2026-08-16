# Feature Specification: Export CSV — neutralisation injection de formules (Closes #4169)

**Feature Branch**: `fix/4169-csv-formula-injection`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4169 (P2, api, security — OWASP CSV Formula Injection)

## Contexte

Les exports CSV du backend écrivent les cellules brutes sans neutraliser les
préfixes de formule (`=`, `+`, `-`, `@`, TAB, CR). Un nom d'employé, une note
ou une valeur d'audit commençant par un tel préfixe est interprété comme une
formule par Excel/LibreOffice à l'ouverture — vecteur classique d'exfiltration.

Un correctif partiel existait (`PayrollAccountingExportService::neutralizeCsvCell`,
issue #2223) mais **privé à un seul service** ; trois autres points d'export
restaient vulnérables.

## User Stories & Testing

### User Story 1 — Un export ne peut pas exécuter de formule (P1)

En tant que manager RH exportant un CSV, je veux que des données contrôlées par
les employés (noms, notes) ne soient jamais interprétées comme formules.

**Acceptance Scenarios**:
1. Given un employé nommé `=cmd|…`, When export CSV employés, Then la cellule
   est `'=cmd|…` (apostrophe) — jamais `=cmd|…` en tête.
2. Given une valeur numérique négative (`-1234.5`, paie), When export, Then la
   cellule reste `-1234.5` (non préfixée).
3. Given un export d'audit contenant `+1+1` dans une valeur JSON, When export,
   Then la cellule est neutralisée.
4. Given les 4 sites d'export, When vérification, Then ils utilisent tous le
   même helper (`CsvCellSanitizer`), sans duplication.

### Edge Cases

- Cellule déjà préfixée `'=cmd|` : pas de double préfixe.
- `null` → cellule vide ; chaîne vide → cellule vide.
- Valeurs numériques (int/float/string numérique) : intactes.
- `\t` et `\r` en tête : neutralisés (OWASP).

## Requirements

### Functional Requirements

- **FR-001**: `App\Support\CsvCellSanitizer::neutralize(mixed): string` centralise
  la garde : préfixe `'` si la cellule (non numérique) commence par
  `= + - @ TAB CR` ; sinon inchangée ; `null` → `''`.
- **FR-002**: les 4 sites utilisent le helper : `ExportController::toCsv`,
  `AuditLogController` (CSV), `AttendanceMonthlyReportService::toCsv`,
  `PayrollAccountingExportService` (remplace la méthode privée #2223).
- **FR-003**: les montants numériques négatifs ne sont jamais préfixés.
- **FR-004**: test unitaire du sanitizer + test de régression sur l'export
  employés (`'=cmd|` dans le CSV).

## Success Criteria

- **SC-001**: `CsvCellSanitizerTest` vert (5 scénarios).
- **SC-002**: test feature export employés avec nom `=cmd|` → CSV neutralisé.
- **SC-003**: PHPStan strict vert, Pint propre.
- **SC-004**: `grep neutralizeCsvCell` → plus aucune méthode privée dupliquée.

## Assumptions

- `fputcsv` (échappement structurel `"`/`,`/newline) reste en place — le
  sanitizer s'applique AVANT, sur le contenu de la cellule.
- Le préfixe apostrophe est affiché littéralement par Excel (comportement
  standard OWASP).

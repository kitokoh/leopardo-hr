# ISSUE_3726 — Import CSV employés : race check-then-create → 500

**Statut**: Fixed (PR `fix/3726-csv-import-race`) · **Priorité**: P3 · **Module**: HR

## Constat (audit 360° 2026-08-15, A-03)

`EmployeeImportController.php` : `exists()` par compagnie (l. ~110) puis
`Employee::create()` (l. ~134). Deux imports concurrents (ou un doublon arrivé
entre check et insert) → violation de l'index unique global `employees(email)`
(migration `2026_04_17_000105_enforce_global_unique_email_on_employees`) →
`QueryException` 23505 → catch `Throwable` global → **500** + rollback du fichier
entier. Même classe de bug que #3238 (évaluations/paie).

## Correctif

`Employee::create()` + `save()` par ligne enveloppés d'un `catch (QueryException)`
ciblé SQLSTATE `23505` (pattern `PartnerService` / `PayrollService` #3238) :

- ligne skippée → `errors[]` (`line` exacte + message « Email ... existe deja
  (doublon concurrent) »), `skipped++`, `continue` ;
- les autres lignes valides sont importées (pas de rollback global) ;
- erreurs hors 23505 → `throw $e` → catch global → 500 `EMPLOYEE_IMPORT_FAILED`
  (code stable, hérité du fix #3725) ;
- réponse 201 si `imported > 0`, sinon 422 — jamais 500 pour un doublon.

## Tests

`api/tests/Feature/HR/EmployeeImportRaceTest.php` :

1. email dupliqué dans le même fichier → 201, `imported=1`, `skipped=1`,
   `errors[0].line=3` ;
2. race concurrente simulée (hook `creating` + insert concurrent SQL) →
   422, ligne skippée, jamais 500 ;
3. ligne en conflit + lignes valides → 201, `imported=2`, `skipped=1`.

## Artifacts spec-kit

`.specify/features/3726-csv-import-race/{spec,plan,tasks}.md`

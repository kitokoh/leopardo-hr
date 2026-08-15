# Tasks: Import CSV employés — race check-then-create (Closes #3726)

**Spec**: `.specify/features/3726-csv-import-race/spec.md`
**Plan**: `.specify/features/3726-csv-import-race/plan.md`

- [x] T1. Claim : issue #3726 self-assignée + branche `fix/3726-csv-import-race` poussée (protocole #2400)
- [ ] T2. `EmployeeImportController::import()` — try/catch `QueryException` 23505 par ligne (skip + `errors[]`, `continue`)
- [ ] T3. Test `EmployeeImportRaceTest` — simulation de la race (hook `creating` + insert concurrent SQL) : 201 + ligne skippée, jamais 500
- [ ] T4. Test complémentaire : email dupliqué dans le même fichier → 2ème ligne skippée proprement
- [ ] T5. Vérifs locales : `phpunit` HR + `phpstan` strict + `pint`
- [ ] T6. CHANGELOG (`### Fixed`) + `docs/specifications/ISSUE_3726.md`
- [ ] T7. PR `fix(api): import CSV — course exists/create → 23505 par ligne, plus de 500 (Closes #3726)` → CI verte → merge → suppression branche

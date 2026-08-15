# Plan: Import CSV employés — race check-then-create (Closes #3726)

**Spec**: `.specify/features/3726-csv-import-race/spec.md`
**Issue**: #3726

## Architecture

Aucun changement structurel — modification ciblée du contrôleur
`api/app/Modules/HR/Interfaces/Api/V1/Controllers/EmployeeImportController.php`
dans la boucle d'import, en miroir du pattern déjà établi par #3238
(`PayrollService` + `PartnerService`) :

```
foreach ($lines ...) {
    // ... validations existantes (inchangées) ...
    try {
        $employee = Employee::create($fillData);
        $employee->company_id = $companyId;
        $employee->status = $status;
        $employee->save();
        $imported++;
    } catch (QueryException $e) {
        // #3726 : course exists()/create() rattrapée par l'index unique global.
        if ($e->getCode() === '23505') {
            $errors[] = ['line' => $index + 2, 'error' => "Email {$row['email']} existe deja (doublon concurrent)"];
            $skipped++;
            continue;
        }
        throw $e; // erreur fatale → catch Throwable global (EMPLOYEE_IMPORT_FAILED)
    }
}
```

### Pourquoi pas de rollback ligne

La transaction couvre tout le fichier ; un 23505 est un conflit métier attendu,
pas une corruption. Continuer préserve les lignes valides (SC-001/SC-003 de la
spec). Les erreurs fatales rejettent via le `throw $e` → catch global.

### Fichiers touchés

| Fichier | Action |
|---|---|
| `api/app/Modules/HR/Interfaces/Api/V1/Controllers/EmployeeImportController.php` | try/catch 23505 par ligne |
| `api/tests/Feature/HR/EmployeeImportRaceTest.php` | nouveau — régression 23505 |
| `CHANGELOG.md` | entrée `### Fixed` |
| `.specify/features/3726-csv-import-race/{spec,plan,tasks}.md` | spec-kit |
| `docs/specifications/ISSUE_3726.md` | spec courte (convention dépôt) |

## Risques

- **Test de la race** : non déterministe par double-envoi réel → le test simule
  la course via un hook modèle : au premier `Employee::creating`, on insère un
  concurrent direct en SQL (même email) pour forcer le 23505 sur le `save()`.
- Aucun impact hors du contrôleur ; pas de migration.

# ISSUE #3726 — Import CSV employés : race check-then-create → 500

> Spec-kit — audit 360° 2026-08-15, constat A-03. Branche : `fix/3726-csv-import-duplicate-race`.

## Problème

`EmployeeImportController::import` vérifiait `exists()` par compagnie puis
`Employee::create` sous une **transaction globale**. Tout doublon arrivé entre
le check et l'insert (deux imports concurrents, même email dans deux fichiers)
produisait une violation d'unicité (SQLSTATE 23505) qui **empoisonne la
transaction PostgreSQL entière** → rollback de toutes les lignes saines + 500.
Même classe de bug que #3238 (évaluations/paie), endpoint non couvert par ce fix.

## Décision

L'import a déjà une sémantique de **succès partiel** (`imported`/`skipped`/
`errors` par ligne) : la transaction globale tout-ou-rien contredisait cette
sémantique. Elle est retirée ; chaque ligne devient indépendante et la race
est rattrapée ligne par ligne.

## Comportement cible

| Cas | Avant | Après |
|-----|-------|-------|
| Doublon connu (`exists()`) | ligne skippée | inchangé |
| Race check-then-create (23505) | rollback global + 500 | **ligne skippée**, autres lignes importées, 201/422 |
| Erreur DB systémique (connexion, etc.) | rollback + 500 | 500 `EMPLOYEE_IMPORT_FAILED` (inchangé, dernier recours) |
| Ligne invalide (validation) | ligne skippée | inchangé |

## Critères d'acceptation

1. Violation d'unicité concurrente → ligne skippée avec message « existe
   deja », réponse jamais 500 (test de régression déterministe via observer
   `creating` qui pré-insère l'email avant l'INSERT Eloquent).
2. Lignes saines du même fichier importées (pas de rollback global).
3. Codes d'erreur stables conservés (#3725) : `EMPLOYEE_IMPORT_FAILED` reste
   le dernier recours pour les erreurs non-23505.
4. PHPStan strict + Backend Coverage verts (CI).

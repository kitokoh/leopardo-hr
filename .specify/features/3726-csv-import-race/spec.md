# Feature Specification: Import CSV employés — race check-then-create (Closes #3726)

**Feature Branch**: `fix/3726-csv-import-race`
**Created**: 2026-08-15 | **Status**: Draft → In progress
**Issue**: #3726 (T003, P3, api)

## Contexte

`EmployeeImportController::import()` fait un `exists()` par compagnie (l. 110-116)
puis un `Employee::create()` (l. 134). Entre les deux, un import concurrent (ou un
doublon déjà présent, ou un second envoi simultané) viole l'index unique global
`employees(email)` (migration `enforce_global_unique_email_on_employees`) →
`QueryException` SQLSTATE 23505 → catch `Throwable` global → **500**. Même classe
de bug que #3238 (évaluations/paie), déjà corrigée côté Payroll via le pattern
« catch 23505 → conflit métier » (voir `PayrollService::persist`).

## User Stories & Testing

### User Story 1 — Import robuste face aux doublons concurrents (P1)

En tant que manager RH, je veux qu'un email dupliqué (même import, import
concurrent, ou déjà présent en base) soit signalé **par ligne** sans faire
échouer tout l'import en 500.

**Why this priority**: le 500 fait perdre tout le fichier (rollback global) alors
que les autres lignes sont valides ; l'utilisateur ne peut pas savoir quelles
lignes sont en cause.

**Acceptance Scenarios**:
1. Given un CSV avec deux lignes partageant le même email, When import, Then la
   1ère ligne est importée (201), la 2ème est dans `errors[]` avec `line` exacte
   et message « existe déjà », jamais de 500.
2. Given un email présent en base mais inséré entre le check `exists()` et le
   `create()` (race), When import, Then la ligne est skippée + rapportée en
   `errors[]` (422 si aucune ligne importée, 201 sinon) — jamais 500.
3. Given une violation unique sur une ligne, When import, Then les autres lignes
   valides sont **quand même** importées (pas de rollback global).
4. Given une erreur non-unicité (ex. contrainte CHECK, DB down), When import,
   Then 500 `EMPLOYEE_IMPORT_FAILED` (code stable, pas de message brut — hérité
   du fix #3725).

### Edge Cases

- Deux imports simultanés du même fichier : chaque ligne en conflit → erreur
  ligne, les lignes distinctes passent.
- Ligne en double **dans le même fichier** : le `exists()` rattrape le 2ème
  (chemin normal), le catch 23505 ne sert que de filet de sécurité — pas de
  double comptage `imported`.
- `count($values) !== count($headers)` : comportement inchangé (erreur ligne).

## Requirements

### Functional Requirements

- **FR-001**: `Employee::create()` + `save()` par ligne DOIVENT être enveloppés
  d'un catch `QueryException` ciblé SQLSTATE `23505`.
- **FR-002**: sur 23505, la ligne DOIT être comptée `skipped`, ajoutée à
  `errors[]` (`line` + message stable), et le traitement des lignes suivantes
  DOIT continuer.
- **FR-003**: le catch `Throwable` global DOIT rester pour les erreurs hors
  23505 et renvoyer le code stable `EMPLOYEE_IMPORT_FAILED` (pas de message brut).
- **FR-004**: une violation unique NE DOIT PAS déclencher `DB::rollBack()` global
  (transaction poursuivie) — seules les erreurs fatales rollback.
- **FR-005**: test de régression automatisé couvrant le chemin 23505.

## Success Criteria

- **SC-001**: un import avec email dupliqué retourne 201/422 (jamais 500), avec
  `errors[].line` exact.
- **SC-002**: le test de régression `EmployeeImportRaceTest` passe en CI.
- **SC-003**: PHPStan strict (level 8) vert, Pint propre, tests HR verts.

## Assumptions

- L'index unique global `employees(email)` existe déjà (migration
  `2026_04_17_000105_enforce_global_unique_email_on_employees`).
- Le code d'erreur SQLSTATE reste `23505` sur PostgreSQL (seule DB supportée).
- Le comportement `exists()` pré-check reste (UX : message propre hors race).

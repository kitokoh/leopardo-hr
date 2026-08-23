# Feature Specification: Tests Feature + gate coverage module Accounting (issue #5228)

**Feature Branch**: `mod/accounting/5228-tests-gate`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Module**: `accounting` (Phase A) — périmètre : `api/tests/Feature/Accounting/**`,
`.github/workflows/accounting-ci.yml` (nouveau), spec, CHANGELOG. Aucun
fichier touché par les PRs en vol (#5337 CRUD contacts, #5341 PDF, #5346
workflow) — uniquement le socle mergé (#5221).

## Contexte

Issue #5228 — suite Feature complète du module Accounting (Phase A) + gate de
coverage CI. État réel au 2026-08-23 :

| Brique | Existant (main) | Gap |
|---|---|---|
| Tests socle | ✅ `AccountingDataModelTest` + `AccountingTenantIsolationTest` (9 tests, livrés avec #5221) | — |
| Couverture relations/enums | ⚠️ 71,4 % (3 méthodes de relation + `ContactSource` non exercés) | **Consolider** |
| **Gate coverage module ≥ 70 %** | ❌ aucun workflow dédié | **Créer `accounting-ci.yml`** (pattern `payroll-ci.yml` F-14) |

## Décisions

1. **`AccountingModelRelationsTest`** (nouveau, 6 tests) : relations non
   appelées par le test existant (`documents()`, `document()` ×2, `lines()`/
   `payments()`/`contact()`), `ContactSource::values()`, casts array de
   `AccountingSettings` → **couverture module = 100 %** (14/14 statements,
   mesurée localement pcov) — marge large sur le seuil 70 %.
2. **Gate CI `accounting-ci.yml`** : pattern `payroll-ci.yml` (postgres 16 +
   redis services, setup-backend-db, pcov, clover, gate python sur
   `app/Modules/Accounting/`, seuil **70 %**, `workflow_dispatch`). Déclenché
   sur les chemins du module. Non requis par la protection de branche (module
   greenfield — les 5 checks globaux restent la porte) mais **bloquant pour le
   DoD** : la PR qui fait chuter le module < 70 % est rouge.
3. **CRUD/workflow/PDF** (#5222/#5223/#5224) : leurs tests seront livrés par
   leurs PRs (pas de doublon ni de dépendance ici) — la gate mesurera le
   module entier dès qu'ils seront mergés.

## User Scenarios & Testing

### US1 — Le module Accounting est testé et couvert (DoD)

**Independent Test**: `php artisan test tests/Feature/Accounting` → 15/15
verts ; coverage pcov = 100 %.

**Acceptance Scenarios**:

1. **Given** le socle mergé, **When** la suite Feature Accounting tourne,
   **Then** 15 tests verts (modèles, enums, casts, relations, isolation
   tenant fail-closed).
2. **Given** le clover, **When** le gate python s'exécute, **Then** le module
   `app/Modules/Accounting` est ≥ 70 % (constaté 100 %).
3. **Given** une PR touchant le module, **When** elle fait chuter la
   couverture < 70 %, **Then** le check `Gate coverage Accounting` est rouge.

## Edge Cases

- **Duplication d'unicité** : l'unicité settings est déjà testée (pattern
  savepoint #4978) — pas de doublon dans ce PR.
- **25P02 en cascade** : tout test d'exception DB doit passer par un savepoint
  (`DB::transaction`, savepoints => true) — pattern #4978.
- **`current_company` en test** : posé via `$this->app->instance(...)` (le
  scope `BelongsToCompany` lit le binding, fail-closed sinon).

## Deliverables

- [x] Spec `.specify/features/5228-accounting-tests-gate/spec.md`
- [x] `api/tests/Feature/Accounting/AccountingModelRelationsTest.php` (6 tests)
- [x] `.github/workflows/accounting-ci.yml` — gate coverage ≥ 70 % (pattern payroll-ci)
- [x] CHANGELOG `[Unreleased]` + PR `Closes #5228`

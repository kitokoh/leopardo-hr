> **Note (2026-09-01, audit #6599)** : le module `SmartAttendance` a été fusionné dans `api/app/Modules/Attendance` (ADR-0016 Phase 5, 2026-08-24). Les chemins `Modules/SmartAttendance/**` ci-dessous sont **historiques** — l'action/module vit désormais sous `Modules/Attendance/**`.

# Feature Specification: Suite Feature rouge — régression fillable #3677 (issue #4151)

**Feature Branch**: `fix/4151-fillable-regression-suite`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA vérifié 2026-08-16 — le durcissement `$fillable` du merge #3677 a retiré `company_id`, `role`, `manager_role`, `status`, `salary_base` du fillable `Employee` (et `status` de `User` / `status`+`two_fa_secret` de `SuperAdmin`), et aligné 3 contrôleurs — mais **~280 sites** (274 `create()` + 5 `update()` + 1 `firstOrCreate()`) dans les tests ET le code applicatif passent encore ces clés dans `create([...])`/`update([...])` → Eloquent les **abandonne silencieusement** : les « managers » de test sont créés `role=employee`, `status=null`, `company_id=null` → 403 en cascade, isolation multi-tenant faussée, suite Feature rouge sur main depuis le merge #3677 (masqué par la famine CI #3545).

## Problème

1. **Tests** : `Employee::query()->create(['company_id'=>…, 'role'=>'manager', …])` — les champs sensibles sont silencieusement droppés → `ManagerValidationTest` (5/6 échecs), `SmartAttendance` (25/28 échecs), et la quasi-totalité de la suite Feature.
2. **App** : `VerifyTrialSignup::provisionTrialCompany` crée le manager de trial `role=employee` (onboarding trial KO) ; `PlatformUserController::update/destroy/activate/deactivate/suspend` ne changent jamais le `status` SuperAdmin (le compte reste actif après « désactivation ») ; `DemoDzSeeder` crée les comptes démo sans rôle/statut.
3. **Faux positifs exclus** : `->factory()->create()` (factories `Model::unguarded` — non affectées) et `$user->companyRequests()->create()` (create de relation → modèle `CompanyRequest`, pas `User`).

## Décision

Pattern canonique #4077/#4079 — **séparer l'assignation de masse des champs sensibles** :

- `create([...fillable...])` puis champs sensibles posés explicitement + `save()` (code applicatif, style #4079).
- Tests : `create([...fillable...])` puis `$model->forceFill([...sensibles...])->save()` (transformation mécanique validée `php -l` + suite).
- `update(['status' => …])` → `forceFill([...])->save()` (ou set explicite + `save()` en app).
- `firstOrCreate` : les attributs de **lookup** (dont `company_id`) restent dans le 1er tableau ; les clés sensibles sont posées après création seulement (`wasRecentlyCreated` — un compte existant garde son rôle/statut).

## User Scenarios & Testing

### User Story 1 — La suite Feature redevient verte (Priority: P0)

**Independent Test**: `php artisan test --testsuite=Feature` vert en CI (PostgreSQL 16 + Redis 7).

**Acceptance Scenarios**:

1. **Given** un test créant un manager via `Employee::query()->create([... 'role' => 'manager' ...])`, **When** il s'exécute, **Then** le manager a `role=manager` en base (zéro clé sensible abandonnée).
2. **Given** un `create()` avec `company_id`, **When** le test s'exécute, **Then** l'employé est scopé au bon tenant (isolation multi-tenant réelle).
3. **Given** le parcours de désactivation SuperAdmin, **When** `PlatformUserController::destroy` est appelé, **Then** `status=deactivated` est persisté (audit + révocation tokens conservés).
4. **Given** un compte démo existant, **When** le seeder rejoue, **Then** son rôle/statut ne sont pas réécrits (`wasRecentlyCreated`).
5. **Given** la base de code, **When** on grep `create([...])` sur Employee/User/SuperAdmin, **Then** zéro site restant avec les clés sensibles.

## Validation locale (PHP 8.4.24 / PostgreSQL 16.15 / Redis)

- 68 fichiers transformés (65 tests + 3 app/db) ; `php -l` vert sur 100 % des fichiers modifiés.
- `ManagerValidationTest` + `AuthLoginTest` + `AbsenceApproveTest` + `AuthServiceTest` verts (cible avant #4151 : rouges).
- Suite Feature complète exécutée localement avant merge.

## Edge Cases

- Commentaires en fin de ligne dans les tableaux (`// …`) : attachés à l'entrée, jamais entre la valeur et la virgule (parse PHP préservé).
- `firstOrCreate` multi-tableaux : le premier tableau sert de lookup (`where`) — `company_id` y reste.
- Relations Eloquent (`$user->companyRequests()->create()`) : hors périmètre (modèle cible ≠ Employee/User/SuperAdmin).
- `forceCreate()` (3 sites) : bypass fillable natif — aucun changement nécessaire.

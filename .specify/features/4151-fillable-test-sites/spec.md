> **Note (2026-09-01, audit #6599)** : le module `SmartAttendance` a été fusionné dans `api/app/Modules/Attendance` (ADR-0016 Phase 5, 2026-08-24). Les chemins `Modules/SmartAttendance/**` ci-dessous sont **historiques** — l'action/module vit désormais sous `Modules/Attendance/**`.

# Feature Specification: Suite Feature verte — sites create()/update() réalignés sur le durcissement fillable #3677 (issue #4151)

**Feature Branch**: `fix/4151-fillable-test-sites`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA (2026-08-16) — le durcissement mass-assignment #3677 a retiré `company_id`, `salary_base`, `role`, `manager_role`, `status` du `$fillable` Employee (et `status`/`two_fa_secret` SuperAdmin, `status` User, `company_id` Department, `company_id`/`role`/`manager_role` UserInvitation, `status` SalaryAdvance) — mais ~330 sites dans les tests ET le code applicatif passent encore ces clés dans `create([...])`/`firstOrCreate(...)` → Eloquent les **abandonne silencieusement** : les « managers » de test sont créés `role=employee`, `company_id=null` → 403 en cascade (ManagerValidationTest 5/6, SmartAttendance 25/28, suite Feature rouge sur main depuis #3677).

## Problème

1. **Tests** : `Employee::query()->create(['company_id' => …, 'role' => 'manager', …])` — les champs sensibles ne sont pas fillable → silencieusement abandonnés. 294 sites transformables (287 create/firstOrCreate classiques + 7 `::withoutGlobalScopes()->create`), plus `firstOrCreate` (values array) dans les seeders.
2. **Code applicatif — 3 sites réels** :
   - `VerifyTrialSignup::verify()` (ligne 283) : le manager de trial créé avec `company_id`/`role`/`manager_role`/`status`/`salary_base` dans `create()` → **tous abandonnés** → le manager de trial n'appartient à aucune compagnie et n'a aucun rôle (régression #3677 jamais corrigée sur ce chemin — cause racine probable des 500 trial #3879/#3259).
   - `PartnerDashboardController::userFromAuth()` : `User::firstOrCreate(…, ['status' => 'active'])` → status abandonné à la création.
   - `DemoDzSeeder::seedAccounts()` : `Employee::firstOrCreate(…, values role/manager_role/status/salary_base)` → comptes démo créés `role=employee`.
   - `PlatformUserController` (update/destroy/activate/deactivate/suspend) : `$user->update(['status' => …])` → status **jamais persisté** (SuperAdmin `status` non fillable) → un compte « désactivé » reste actif (sécurité #2630 : tokens révoqués mais compte toujours utilisable).

## Décision

- **Tests** : scinder chaque site — `create([fillable…])` puis assignation explicite des champs sensibles + `save()` (pattern #3677, `// Sensitive fields set explicitly`). Variable capturée (LHS existante ou temp `$createdEmployee`/`$createdUser`/… unique par fichier). Sites `create(array_merge(…))` → `forceCreate` (sémantique merge préservée, aucun champ perdu). `firstOrCreate` → champs sensibles retirés du values array + assignation explicite si `wasRecentlyCreated` (préserve l'idempotence / ne touche pas les lignes existantes).
- **Code applicatif** : même pattern explicite (pas de forceCreate en prod).

## User Scenarios & Testing

### User Story 1 — La suite Feature repasse au vert (Priority: P0)

**Independent Test**: `php artisan test --testsuite=Feature` vert (classes transformées incluses) ; `php -l` OK sur les 83 fichiers modifiés ; `SensitiveFillableGuardTest` vert (aucun champ sensible réintroduit en fillable).

**Acceptance Scenarios**:

1. **Given** `ManagerValidationTest` (le « manager » est créé via `create()`), **When** la suite tourne, **Then** le manager a `role=manager`, `manager_role=rh`, `company_id` défini → `api.manager` répond 200 (fini les 403 en cascade).
2. **Given** un site `create([...])` avec champs sensibles, **When** le code est scanné, **Then** aucun `create([...])` avec `role`/`manager_role`/`company_id`/`status`/`salary_base`/`two_fa_secret` ne subsiste dans `api/tests` (0 restant, hors factory).
3. **Given** `VerifyTrialSignup`, **When** un trial est provisionné, **Then** le manager est créé avec `company_id`, `role=manager`, `manager_role=principal`, `status=active`, `salary_base=0` (assignation explicite + `save()`).

## Validation locale

- `php -l` : 83 fichiers modifiés — 0 erreur de syntaxe.
- Scan de régression (fillable-aware) : 0 site restant avec clés sensibles dans `api/tests` (294 sites transformés + 5 `forceCreate`).
- `SensitiveFillableGuardTest` : aucun `$fillable` modifié (garde intacte).
- CI : Tests + Coverage + PHPStan strict requis pour le merge (branch protection main).

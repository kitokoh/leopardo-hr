# Diagnostic — Suite Feature flaky : 178 échecs cascade SQLSTATE (#4980)

Session QA 2026-08-17/18 — constaté sur `main` (run 32081198888, `f64bed34`).

## Symptômes

- `Tests - Leopardo RH` (tests.yml, suite Feature complète) : 178 failed / 2348 passed.
- 168 erreurs SQLSTATE : `23502 Not null violation` (`cabinet_shares.shared_via`,
  `companies.city`, `users.first_name`, `employees.password_hash`,
  `user_invitations.company_id`), `23503 FK` (`partners_user_id`), et surtout
  **`25P02 current transaction is aborted`** en cascade.
- Intermittent : vert sur `fix/4947-employee-password-hash` (20:28Z) avec le
  même code, rouge sur main et plusieurs branches.

## Cause racine (deux mécanismes distincts qui se cumulent)

### 1. Dérive de contraintes fixture mvp vs vraies migrations (source des 23502)

`api/tests/Support/sql/mvp_schema.pgsql.sql` (schéma manuel `CreatesMvpSchema`)
est **plus permissif** que les vraies migrations (`database/migrations/public`
+ `tenant`) :

| Colonne | Fixture mvp | Vraies migrations |
|---|---|---|
| `users.first_name` / `last_name` | `NOT NULL DEFAULT ''` | `NOT NULL` **sans défaut** |
| `employees.first_name` / `last_name` | `NOT NULL DEFAULT ''` | `NOT NULL` **sans défaut** |
| `companies.plan_id` | NULL | `NOT NULL` + FK `plans(id)` |
| `companies.subscription_start/end` | NULL | `NOT NULL` |
| `companies.status` (défaut) | `'active'` | `'trial'` |
| `users.provider` (défaut) | `'local'` | `'email'` |

Conséquences : les tests qui créent des enregistrements « minimaux » passent
sur la fixture mais lèvent `23502` sur le vrai schéma :

- `User::query()->forceCreate(['email' => …, 'password_hash' => …])` →
  `users.first_name` (ex. `UserEmployeeLinkCrossTenantTest`, corrigé ici ;
  le fix #5034 n'avait ajouté que `first_name`, `last_name` manquait).
- `new Employee(['email' => …])` + `forceFill` → `employees.first_name`
  (ex. `EmployeeTenantIsolationTest` — la version merged référençait même un
  `$slug` hors scope → TypeError PHP 8).
- `Company::query()->create([…])` sans `plan_id`/`subscription_start`/
  `subscription_end` → `companies.plan_id` (première colonne NOT NULL
  violée dans l'ordre de définition).
- `Employee::query()->create([…])` sans `password_hash` (hors fillable) →
  `employees.password_hash`.

Les PRs #5049/#5051/#5055 (paquets A/B/C de #4972) ont **basculé le trait**
`CreatesMvpSchema → RefreshTenantDatabase` sur les 16 fichiers, mais **sans
aligner les fixtures** : ces tests restent en échec `23502` sur main. Cette
branche corrige les 15 fichiers concernés (les fixtures factory de
`ApiTokenControllerTest`, `EmployeeImportRaceTest`, `LeaveWorkflowIntegrationTest`
étaient déjà conformes) + `UserEmployeeLinkCrossTenantTest`.

### 2. Masquage des échecs par la gate de couverture (pourquoi main reste « vert »)

`coverage-gate.yml` (« Backend Coverage (PHP 8.4 + PostgreSQL 16) », check
REQUIS) :

```yaml
run: |
  mkdir -p storage/coverage
  php artisan test --coverage-clover=storage/coverage/clover.xml --coverage-text 2>&1 | tee storage/coverage/summary.txt
```

Le pipe vers `tee` **sans `set -o pipefail`** fait que l'étape réussit même
quand PHPUnit échoue (le code de sortie de `tee` est 0) ; PHPUnit écrit quand
même le Clover en fin de run → la gate n'évalue QUE le pourcentage de
couverture et reste verte avec 178 tests en échec. Les 5 checks requis de main
sont donc verts malgré une suite rouge — traité dans #4978 (ajout de
`set -o pipefail` + extraction de l'erreur causale + JUnit).

### 3. Facteur aggravant : état de schéma dépendant de l'ordre (25P02 en cascade)

Le mélange `CreatesMvpSchema` (136 fichiers restants) et
`RefreshTenantDatabase` dans la même suite rend l'état du schéma dépendant de
l'ordre d'exécution :

- `CreatesMvpSchema::setUpMvpSchema()` force
  `RefreshDatabaseState::$migrated = false` puis remplace le schéma
  `shared_tenants` par la fixture partielle ;
- le fichier `RefreshTenantDatabase` suivant re-migre, mais le `migrate:fresh`
  ne droppe que `public` → les tables tenant de la fixture partielle entrent
  en collision (`42P07 relation already exists`) ou laissent un schéma
  incomplet ;
- en PostgreSQL, la première erreur SQL dans une transaction l'abort
  (`25P02`) → toutes les requêtes suivantes du test échouent en cascade.

Mitigation déjà en place sur main (`tests/RefreshTenantDatabase.php`,
commit 58ac93636) : `DROP SCHEMA IF EXISTS shared_tenants CASCADE` avant le
`migrate:fresh`. Le correctif **structurel** reste la migration complète des
136 fichiers restants vers `RefreshTenantDatabase` (chantier F-13b #1593/#1606).

## Critère d'acceptation

- Suite Feature complète sur base fraîche (PostgreSQL 16, 4 workers) : 0 échec
  sur deux exécutions consécutives (cf. #4978) ;
- `dev-hub/tools/check-test-schema-drift.sh --report-f13` : 140/140 (100 %) ;
- plus aucun `Company::query()->create` sans `plan_id` +
  `subscription_start/end`, plus aucun `new Employee` sans
  `first_name`/`last_name`, plus aucun `forceCreate` User sans
  `first_name`/`last_name` dans `tests/Feature` (gates à venir).

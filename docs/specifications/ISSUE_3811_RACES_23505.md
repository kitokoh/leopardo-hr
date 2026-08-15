# Mini-spec — Issue #3811

## Problème

L'audit 360° 2026-08-15 (expert QA) a relevé 6 races check-then-create : le
garde `exists()` n'est pas atomique sous concurrence → la seconde requête
insère un doublon ou explose en 500 SQL brut (violation d'index unique) :

1. `CommissionService::recordCommissionForPayment` — `commissions.payment_id`
   sans index unique → double commission partenaire sur webhooks concurrents.
2. `SelfServiceController::selfEnroll` — unique `(training_session_id,
   employee_id)` existe, pas de catch 23505.
3. `PublicHolidayController::store` — unique `(company_id, country_code, date,
   year)` existe, pas de catch.
4. `CompanyProvisioningService` — `companies.slug` unique, `resolveUniqueSlug`
   non atomique → 500 sur provisionnements concurrents.
5. `SyncEngineService::applyAttendanceLog` — unique `external_event_id` existe,
   pas de catch → job en échec.
6. `CompanyRequestController::resolveRequestUser` — `firstOrCreate` email non
   atomique.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Insertion concurrente sur contrainte unique | 409/422 idempotent (ou skip propre), jamais de 500 |
| Index unique `commissions_payment_id_unique` | Créé par migration idempotente (dédoublonnage préalable) |
| `CheckThenCreateRaceRegressionTest` | 3 cas verts |
| PHPStan strict / Pint | 0 erreur |

## Correctif

- Migration `database/migrations/public/2026_08_15_000008_add_unique_commissions_payment_id.php`
  (dédoublonnage + `CREATE UNIQUE INDEX IF NOT EXISTS`).
- Catch `QueryException` code `23505` sur les 6 sites → comportement idempotent
  (null / 422 / conflit documenté / re-lecture de l'existant), les autres
  erreurs sont relancées.

## Validation

`CheckThenCreateRaceRegressionTest` (3 tests), suites Payroll vertes, PHPStan
strict `[OK]`, Pint `PASS` ; CI `Tests - Leopardo RH` + `Backend Quality`.

Closes #3811

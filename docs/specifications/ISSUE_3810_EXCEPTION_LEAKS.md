# Mini-spec — Issue #3810

## Problème

L'audit 360° 2026-08-15 (expert QA) a relevé des messages d'exception bruts
exposés dans les réponses JSON de ~10 endpoints API (résiduel #3725 qui n'avait
couvert que 4 contrôleurs) :

- `PayrollRunController` ×4 : `catch (...|\RuntimeException $e) { response()->json(['message' => $e->getMessage()], 422) }` — les messages métier internes français sortent tels quels.
- `SSOController` ×3 (SAML + OIDC authorize/callback).
- `AnnouncementController` ×2 (publish/cancel) : `ValidationException::withMessages(['status' => $e->getMessage()])`.
- `GeoAttendanceController` : `detail => $e->getMessage()` (distances en mètres brutes).
- `RateValidationAdminController` ×2, `SocialContributionController`, `TaxSlabController` : `abort(422, $e->getMessage())`.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `getMessage()` d'exception dans les réponses JSON | Aucun (hors exceptions de domaine à code stable) |
| Erreurs métier | Codes stables (`PAYROLL_RUN_VALIDATION_FAILED`, `SAML_AUTH_FAILED`, `ANNOUNCEMENT_PUBLISH_FAILED`, …) + `localized_message` |
| Détails techniques (SQL, distances, statuts internes) | Logs serveur uniquement |
| Exceptions de domaine (`PayrollRunLockedException`…) | `errorCode()` + `localized_message` (contrat existant préservé) |
| `ExceptionLeakRegressionTest` | 3 cas verts |
| PHPStan strict / Pint | 0 erreur |

## Correctif

- `PayrollRunController` (validate/lock/unlock/regularize) : catches séparés —
  exceptions de domaine → `errorCode()` + message localisé ; `RuntimeException`
  → code stable + `Log::error` (ignores PHPStan documentés : le flow analysis
  ne voit pas les `RuntimeException` de `assertHasPaySlips()`/`unlock()`).
- `SSOController`, `AnnouncementController`, `GeoAttendanceController`,
  `RateValidationAdminController`, `SocialContributionController`,
  `TaxSlabController` : codes stables + logs, suppression du `detail` brut.
- 13 nouveaux codes dans `lang/{fr,en}/errors.php`.

## Validation

`ExceptionLeakRegressionTest` (3 tests HTTP), 35 tests Feature verts
(parmi les suites Payroll/Announcement), PHPStan strict `[OK] No errors`, Pint
`PASS` sur les fichiers touchés ; CI `Tests - Leopardo RH` + `Backend Quality`.

Closes #3810

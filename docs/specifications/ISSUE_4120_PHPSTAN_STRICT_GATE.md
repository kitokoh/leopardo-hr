# ISSUE_4120 — Gate PHPStan strict : baseline obsolète + 15 erreurs restantes

**Statut**: Fixed (PR `fix/4120-phpstan-strict-remaining`) · **Priorité**: P1 · **Module**: CI/api

## Correctif

Après #4108 (10 erreurs réelles + baseline régénéré), 15 erreurs restaient sur
main, toutes dans 2 fichiers de test mergés ensuite :

- `tests/Feature/SmartAttendance/SmartAttendanceFlowTest.php` (11) :
  - propriétés typées (`Company $company`…) assignées depuis `factory()` →
    variables locales avec `@var` puis affectation ;
  - `tearDownMvpSchema()` inexistant (le test n'utilise pas CreatesMvpSchema)
    → drop explicite des 3 tables SmartAttendance ;
  - `geoEvent()` sans types itérables → `@param/@return array<string, mixed>` ;
  - accès `ended_at`/`duration_seconds`/`id` sur `GeoAttendanceSession|null` →
    `firstOrFail()` + `assertNotNull` ;
- `tests/Unit/Services/OidcIdTokenValidatorDerTest.php` (4) :
  `openssl_pkey_new/get_details` peuvent renvoyer `false` →
  `assertNotFalse`/`assertIsArray` + `@var \OpenSSLAsymmetricKey`.

## Validation (locale, PHP 8.4)

- `vendor/bin/phpstan analyse -c phpstan-strict.neon` → **[OK] No errors**
- `-c phpstan-modules.neon` → [OK] No errors
- `vendor/bin/pint --test` sur les 2 fichiers → PASS

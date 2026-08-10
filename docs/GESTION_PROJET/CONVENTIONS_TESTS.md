# Conventions de tests backend (Laravel/PHPUnit)

> Document de référence pour écrire des tests backend fiables dans `api/tests/`.
> Conventions validées en session 2026-08-09/2026-08-10 (audit A-1, #1679).

## 1. `PendingCommand` est LAZY — exécuter explicitement avant les assertions

`Illuminate\Testing\PendingCommand` **n'exécute pas la commande** au moment de
`$this->artisan(...)` : `assertExitCode()`, `assertSuccessful()` et
`assertFailed()` ne font qu'**enregistrer** l'expectation, et la commande est
réellement lancée au `__destruct` de l'objet.

Conséquence — deux cas :

### Cas SÛR : chaînage sans affectation

```php
$this->artisan('ma:commande')->assertSuccessful();
$this->assertDatabaseHas(...); // OK : la commande a tourné
```

L'objet temporaire est détruit à la fin de l'instruction → la commande s'exécute
immédiatement, avant les assertions suivantes.

### Cas DANGEREUX : PendingCommand stocké dans une variable

```php
$cmd = $this->artisan('ma:commande');
$cmd->assertSuccessful();
$this->assertDatabaseHas(...); // ❌ LA COMMANDE N'A PAS ENCORE TOURNÉ
```

La variable maintient l'objet en vie : les assertions DB/fichier/queue qui
suivent constatent l'état **avant** exécution (test « vert » sans rien vérifier,
ou échec à tort). L'exit code n'est vérifié qu'au destruct, en fin de méthode.

### Règle

- Si la commande est stockée (pour `expectsOutput*` ou plusieurs asserts) :
  appeler **`$cmd->run()`** explicitement **avant** toute assertion d'état
  (DB, fichier, queue, notification, HTTP).

```php
/** @var \Illuminate\Testing\PendingCommand $cmd */
$cmd = $this->artisan('audit:purge', ['--older-than' => 12]);
$cmd->expectsOutputToContain('1');
$cmd->assertSuccessful();
$cmd->run(); // ← obligatoire avant les assertions d'état
$this->assertSame(1, DB::table('audit_logs')->count());
```

- Ne jamais chaîner `->assertX()` sur un `$this->artisan(...)` **affecté** à une
  variable sans `run()` avant les assertions d'état.
- Pattern validé : `GdprAnonymizeEmployeeTest`, `PurgeAuditLogsCommandTest`,
  `HrModelSeederTest`, tests S-1/S-2.

## 2. Factories et typage (gate PHPStan strict)

La gate `PHPStan — Strict (Core/Modules/Shared, level 8)` inclut `tests/`.
Larastan ne résout pas toujours le générique des factories (`X::factory()->create()`
→ `Model`) : déclarer explicitement le type attendu (convention repo) :

```php
/** @var Company $company */
$company = Company::factory()->create([...]);
/** @var Employee $employee */
$employee = Employee::factory()->create([...]);
```

Sans cela, tout accès `$company->id`, `$employee->createToken()`, etc. est une
erreur level 8 (`Access to an undefined property Model::$id`, `undefined method
createToken()`) qui casse la CI.

## 3. Alignement sur les vraies migrations

- Les tests Feature utilisent `RefreshTenantDatabase` (vraies migrations public +
  tenant), **pas** le schéma manuel `CreatesMvpSchema` (supprimé progressivement,
  dérive F-13/F-13b).
- Insérer via les **factories** (`Company::factory()`, `Employee::factory()`)
  plutôt que `X::query()->create([...])` : le schéma réel impose des colonnes
  NOT NULL (`plan_id`, `first_name`, ...) que les factories remplissent.
- Toute migration touchant une table existante doit être **additive** et
  **réconcilier** l'existant (pattern `2026_08_09_000001_reconcile_languages_updated_at`),
  jamais early-return sans ALTER additif.

## 4. Règles de base

- Nommage `test_<verbe>_<objet>_<condition>` (snake_case).
- Assertions métier précises (`assertDatabaseHas`, `assertJsonPath`) ; pas
  d'assertion « fumée » uniquement.
- Pas de secrets réels dans les tests (convention #1614).

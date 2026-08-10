# Conventions de tests — Backend (Laravel / PHPUnit / Pest)

> Créé le 2026-08-10 — Issue A-1 (#1679). Complète `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md`
> et `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` (comment exécuter), en se
> concentrant sur le **comment écrire** des tests fiables.

## 1. PendingCommand est LAZY — exigez `run()` explicite (A-1, #1679)

### Le piège

`Illuminate\Testing\PendingCommand` **n'exécute pas la commande au moment de
l'appel** : il l'exécute dans `__destruct()`. Deux cas très différents :

| Pattern | Comportement |
|---|---|
| `$this->artisan('x')->assertExitCode(0);` (chaîné, résultat jeté) | Le `PendingCommand` temporaire est détruit **à la fin de l'instruction** → la commande tourne immédiatement. **SÛR.** |
| `$cmd = $this->artisan('x'); $cmd->assertExitCode(0); …assertions DB…` (assigné à une variable) | Le `PendingCommand` reste vivant jusqu'à la fin de la méthode → **les assertions DB/fichiers/queue qui suivent tournent AVANT l'exécution de la commande**. Le test peut être « vert » sans rien vérifier (assertions vides), ou rouge à tort. **DANGEREUX.** |

### La règle

> **Toute assertion portant sur l'état APRÈS la commande (base, filesystem,
> queue fake, HTTP fake, audit_logs…) exige un `$cmd->run();` explicite avant
> l'assertion**, dans le pattern assigné. Même sans assertion d'état, ajoutez
> `run()` pour la lisibilité : le lecteur voit l'exécution.

```php
/** @var \Illuminate\Testing\PendingCommand $cmd */
$cmd = $this->artisan('biometric:purge-expired', ['--company' => $company->id]);
$cmd->expectsOutputToContain('employe(s)');
$cmd->assertExitCode(0);
$cmd->run(); // exécution immédiate avant les assertions DB (PendingCommand est lazy)

$this->assertNull($employee->fresh()->biometric_face_reference_path);
$this->assertDatabaseHas('audit_logs', ['action' => 'biometric_templates_purged']);
```

### Audit 2026-08-10 (issue A-1, #1679)

- **Corrigés** : `GdprAnonymizeEmployeeTest` (**6 usages**, dont
  `test_anonymize_with_company_scope` repéré en revue finale — assertions DB
  pré-exécution), `PurgeAuditLogsCommandTest` (test 3),
  `BiometricPurgeExpiredTest` (S-1) — `run()` explicite ajouté.
- **Durcis** (conversion du pattern chaîné → pattern assigné + `run()` explicite,
  pour un échec immédiat et l'insensibilité au cycle de vie des temporaires PHP) :
  `AnnouncementControllerTest` (3), `EdgeSilentNodeDetectionTest` (2),
  `PublishScheduledSocialPostsCommandTest` (3), `PublishScheduledPostJobTest` (1),
  `PrecalculatePayrollRunsCommandTest` (6).
- **Vérifiés SÛRS** (aucune assertion d'état post-commande ; le chaînage
  temporaire est détruit en fin d'instruction → exécution immédiate) :
  `MakeModuleCommandTest`, `FeatureRegistryIntegrationTest`,
  `AccrueLeaveBalancesTest`, `HrModelSeederTest` (run() explicite),
  `tests/RefreshTenantDatabase.php` (migrations : résultat jeté → exécution
  avant `setArtisan(null)`), `QueueObservabilityApiTest` (setUp, idem).
- **Convention** : tout NOUVEAU test qui garde un `PendingCommand` en variable
  doit appeler `run()` avant ses assertions d'état. Le pattern chaîné pur est
  toléré sans assertions d'état post-commande, mais `run()` explicite est
  préféré partout (lisibilité + déterminisme).

## 2. PHPStan strict (level 8) inclut les tests

`phpstan-strict.neon` (job « PHPStan — Strict (Core/Modules/Shared, level 8) »)
analyse `app/`, `routes/` **et `tests/`**. Les erreurs fréquentes et leurs fixes :

- `X::factory()->create()` est typé `Model` par Larastan → **annotation `@var`
  avant l'appel** :
  ```php
  /** @var Company $company */
  $company = Company::factory()->create([...]);
  ```
- `$run->fresh()->status` → `$run->refresh(); $run->status` (fresh() est `TModel|null`).
- `assertIsFloat($x)` sur un `float` déjà typé → assertion plus forte (`assertSame(4.0, $x)`).
- Propriété de test écrite mais jamais lue → supprimer ou utiliser.

## 3. Migrations : additives uniquement

Ne jamais réécrire une migration déjà mergée (les env déjà migrés ne la
rejoueront pas). Créer une **migration additive de rattrapage**
(`2026_08_09_00000N_…`) et commenter la migration d'origine avec un ⚠️
pointant vers elle (cf. S-3, #1663).

## 4. Déterminisme temporel

`Carbon::setTestNow()` + `Carbon::setTestNow()` pour restaurer, jamais de
`sleep()` (cf. `AnnouncementControllerTest`). Les données de test utilisent les
vraies migrations via `RefreshTenantDatabase` — `CreatesMvpSchema` est
déprécié (F-13).

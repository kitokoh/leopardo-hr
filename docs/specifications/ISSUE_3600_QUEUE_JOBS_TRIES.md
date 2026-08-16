# Issue #3600 — Jobs queue sans $tries/$backoff/failed() : retries en rafale puis échec silencieux

## Problème

`ProvisionDemoTenantJob` (provisioning trial) attrapait toutes les `Throwable`,
loggait, marquait la ligne `trial_provisionings` en `failed`… **sans rethrow** :
le job « réussissait » → aucun retry, aucun `failed()` → un provisioning cassé
était invisible (le prospect ne reçoit jamais son accès, aucun signal).

## Correctif

- `public int $tries = 5;` + `backoff(): [30, 60, 120, 300]` (retries espacés).
- Le `catch` **rethrow** désormais l'exception : la queue re-tente (tries/backoff),
  le statut 'failed' reste visible pendant les retries, un succès ultérieur repasse
  à 'ready'.
- `failed(Throwable $e)` : alerte log + marquage final 'failed' idempotent.

## Critères de succès

1. `rg 'tries' api/app/Jobs/ProvisionDemoTenantJob.php` → présent.
2. `rg 'throw \$e'` dans le catch → présent.
3. Aucune colonne inexistante utilisée dans les updates (`failed_at` absent de la
   table → non utilisé).

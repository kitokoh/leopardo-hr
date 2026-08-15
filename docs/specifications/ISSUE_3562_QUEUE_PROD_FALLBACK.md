# Mini-spécification — Issue #3562

## Objectif

Empêcher qu'une production sans `QUEUE_CONNECTION` explicite exécute les jobs lourds dans la requête web (driver `sync`), alors que le worker redis est déployé (`render.yaml` → `leopardo-queue-worker`).

## Constat

`config/queue.php` défaut = `sync`. Si `QUEUE_CONNECTION` manque dans l'env de prod (env partiel, config:cache antérieur, blueprint Render incomplet), `Queue::dispatch()` s'exécute en ligne → timeouts web, jobs lourds (paie, exports, mails) bloquants.

## Décision

Défaut contextuel : `env('QUEUE_CONNECTION', app()->environment('production') ? 'redis' : 'sync')`.
- Production sans override → `redis` (worker déployé, comportement attendu).
- Dev/test → `sync` (inchangé, simplicité locale).
- `QUEUE_CONNECTION` explicite gagne toujours.

## Critères d'acceptation

1. `QUEUE_CONNECTION` absent + `APP_ENV=production` → `config('queue.default') === 'redis'`.
2. `QUEUE_CONNECTION` absent + `APP_ENV=testing` → `'sync'` (tests inchangés).
3. `QUEUE_CONNECTION=redis` explicite → `'redis'`.
4. PHPStan Strict level 8 : 0 erreur.

## Plan de retour arrière

Réversion du commit ; aucune migration ni donnée.

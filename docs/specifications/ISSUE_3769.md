# Mini-spec — Issue #3769

## Intention
Éviter que les jobs d’emails, notifications et paie soient exécutés en synchrone dans les requêtes de production, tout en conservant un environnement local/test déterministe.

## Contrat attendu

| Surface | Règle |
|---|---|
| Production Render | `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_URL` fourni par l’environnement |
| Worker | Service `leopardo-queue-worker` avec `queue:work --tries=3 --backoff=60 --max-time=3600` |
| Jobs critiques | `tries` et `backoff` explicites, avec journalisation `failed()` lorsque nécessaire |
| Build Composer | La configuration queue ne doit pas appeler `app()->environment()` pendant `package:discover` |
| Local/test | `QUEUE_CONNECTION=sync` peut être fourni par `.env.testing`/`phpunit.xml` |

## Correctif

`api/config/queue.php` lit désormais `APP_ENV` via `env()` directement pour choisir le fallback Redis production ou sync local. Cela évite la résolution prématurée de la classe `env` qui faisait échouer `composer install` dans le build Render avant même le démarrage de l’application.

## Validation

`php -l api/config/queue.php` et `git diff --check` passent localement. Les dépendances vendor n’étant pas présentes dans le sandbox, `package:discover` sera validé par le build Render suivant.

# Version 4.1.33 | 11 Avril 2026

## Règle d'équipe

Pour le backend Laravel, la validation locale standard se fait **dans Docker avant le push**.
La CI GitHub confirme ensuite le résultat ; elle ne doit plus être le premier détecteur d'erreurs.

## Ordre de validation attendu

1. Vérifier que Docker Desktop est opérationnel
2. Lancer les tests backend dans le conteneur `app`
3. Corriger localement
4. Commit / push
5. Laisser la CI confirmer

## Vérification minimale Docker

Depuis la racine du repo :

```powershell
docker context ls
docker version
docker ps
```

Si `docker version` ou `docker ps` ne répond pas rapidement, considérer Docker comme **non prêt** et corriger d'abord l'environnement local.

## Commandes officielles backend (local)

```powershell
cd api
docker compose up -d
docker compose exec app php -v
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec -e APP_ENV=testing app php artisan test
```

> Note: sans `APP_ENV=testing`, certains tests web peuvent retourner `419` (CSRF) en local.

Alternative Windows (1 commande):

```powershell
cd api
.\start-local.ps1 -SeedDemo -RunTests
```

## État de référence au 11 Avril 2026

- `api/docker-compose.yml` est désormais versionné
- Runtime local backend aligné sur PHP 8.4 (cohérent avec la CI)
- Service principal attendu : `app`
- Base locale : PostgreSQL 16 (`pgsql`)

## Temps de build (important)

- Le **premier** `docker compose up --build` est lent (image PHP à construire)
- Les démarrages suivants sont rapides (`docker compose up -d`)
- Rebuild complet requis seulement si le `Dockerfile` change ou si vous forcez `--build`

## Fallback temporaire

Si Docker local est indisponible malgré tout :

- utiliser la CI GitHub comme confirmation finale
- ouvrir un ticket "blocage environnement Docker local" et le traiter avant le prochain lot backend

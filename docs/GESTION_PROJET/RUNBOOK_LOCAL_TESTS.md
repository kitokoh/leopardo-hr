# RUNBOOK - TESTS LOCAUX (DOCKER D'ABORD)
# Version 4.1.29 | 09 Avril 2026

## Règle d'équipe

Pour le backend Laravel, la validation locale standard se fait **dans Docker avant le push**.
La CI GitHub confirme ensuite le résultat ; elle ne doit plus être le premier détecteur d'erreurs.

## Ordre de validation attendu

1. Vérifier que Docker Desktop est réellement opérationnel
2. Lancer les tests backend dans le conteneur PHP du projet
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

## Commande cible backend

Le projet doit exposer une commande Docker stable pour le backend, par exemple :

```powershell
docker compose exec app php artisan test
```

ou, si l'équipe utilise Laravel Sail :

```powershell
cd api
./vendor/bin/sail test
```

## État constaté le 09 Avril 2026

- Le repo contient `laravel/sail` dans `api/composer.json`
- Aucun `docker-compose.yml` versionné n'est actuellement présent à la racine du repo ni dans `api/`
- Sur cette machine, `docker context ls` répond, mais `docker version` / `docker ps` ont expiré sans réponse exploitable

## Action obligatoire avant prochain lot backend

1. Décider et versionner la méthode Docker officielle du projet
2. Documenter la commande exacte dans ce fichier si elle change
3. Utiliser cette commande avant toute PR backend significative

## Fallback temporaire

Tant que la commande Docker locale n'est pas stable :

- continuer à s'appuyer sur la CI GitHub pour confirmation
- mais traiter l'absence de validation Docker locale comme un **blocage technique à lever**

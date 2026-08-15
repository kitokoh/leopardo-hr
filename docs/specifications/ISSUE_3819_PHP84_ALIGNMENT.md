# Mini-spec — Issue #3819

## Intention
Rendre la matrice PHP déclarée cohérente : le lock Composer verrouille des
composants Symfony 8.x exigeant PHP >= 8.4.1, mais `composer.json` et la doc
déclarent `^8.2` / `>= 8.2` → `composer install` échoue sur un environnement
PHP 8.3 documenté (autoload absent, tests API impossibles).

## Décision (stratégie 1 — standardiser sur PHP 8.4.1+)
L'écosystème cible déjà PHP 8.4 : CI (`tests.yml` : `php-version: 8.4`),
`api/Dockerfile.prod` (`frankenphp:php8.4-alpine`), `api/docker/php84/`,
README (PHP 8.4). La déclaration Composer et la doc étaient les seuls
résidus 8.2.

## Correctif
- `api/composer.json` : `"php": "^8.2"` → `"^8.4.1"`
- `api/composer.lock` : `platform.php` `^8.2` → `^8.4.1`
- `DEVELOPMENT.md` : `PHP >= 8.2` → `PHP >= 8.4.1` (mention Symfony 8.x)

## Validation
- `python3 -c "import json; json.load(...)"` sur les deux fichiers : OK
- Aucun `composer update` non déterministe ajouté (diff minimale, 3 lignes)
- `composer install` sera validé par le build CI suivant (PHP 8.4)

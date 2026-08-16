# ISSUE_4110 — composer.lock désynchronisé (CI Backend bloquée)

**Statut**: Fixed (PR `fix/4110-composer-lock-sync`) · **Priorité**: P1 · **Module**: CI

## Constat

`composer validate` sur main : « lock file is not up to date with the latest
changes in composer.json » → tous les jobs Backend échouaient avant l'exécution
des contrôles (Pint, PHPStan, PHPUnit). Cause : montée de la contrainte PHP
`^8.4.1` (#3844) sans régénération du lock.

## Correctif

`composer update --lock --ignore-platform-req=php` (aucune version de paquet
changée) → nouveau `content-hash`, diff de 1 ligne. Vérifié :
`composer validate` OK (serialization composer 2.9 conservée).

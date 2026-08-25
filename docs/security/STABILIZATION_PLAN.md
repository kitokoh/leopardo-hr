# Plan de stabilisation sécurité — premier lot

## Objectif

Ce document suit le premier lot de durcissement du monorepo Laravel/Next.js. Les changements sont volontairement progressifs : les protections existantes sont renforcées sans basculer silencieusement des environnements de production vers un comportement incompatible.

## Mesures implémentées dans ce lot

| Domaine | Mesure | État |
|---|---|---|
| Isolation tenant | Tests fail-closed : toute requête ou création sur un modèle tenant sans contexte société doit lever `TenantContextMissingException` lorsque le garde tenant est actif. | Implémenté |
| CSP Next.js | La CSP peut être passée de `Report-Only` à `Content-Security-Policy` uniquement avec `CSP_ENFORCE=true`. | Implémenté |
| Images | Les SVG distants sont désactivés par défaut via `NEXT_IMAGE_ALLOW_SVG=false`. | Implémenté |
| Auth proxy | Les routes login et callback Google ne relaient plus les en-têtes `X-Forwarded-For` ou `X-Real-IP` fournis par le client. | Implémenté |
| Déconnexion | Le cookie est supprimé localement, mais un échec de révocation backend est signalé en HTTP 502 au lieu d’être présenté comme une réussite. | Implémenté |
| Webhooks Stripe | Les en-têtes de signature malformés sont rejetés proprement et fail-closed, sans exception de parsing. | Implémenté |
| CI/CD | La permission globale du workflow de tests est passée en lecture seule ; l’écriture est limitée au job de formatage qui en a besoin. | Implémenté |
| Tests frontend | Tests hermétiques ajoutés pour succès, échec de révocation et absence de cookie sur la route logout. | Implémenté |

## Critères de passage à la phase suivante

La phase suivante doit confirmer l’exécution des tests backend sur PostgreSQL/Redis, des tests Jest/TypeScript côté Next.js, de la validation YAML des workflows et de `composer audit`. Aucun basculement global de CSP ne doit être effectué avant collecte et revue des rapports CSP en staging ou production contrôlée.

## Travaux prioritaires encore ouverts

La revue complète des contrôleurs et policies doit continuer sur les endpoints d’export, de téléchargement, de paie et de documents. Les webhooks doivent ensuite être vérifiés événement par événement pour garantir l’idempotence persistée et la résistance au rejeu. Enfin, les flux offline/mobile, les uploads, les sauvegardes chiffrées et les environnements staging/production doivent faire l’objet de tests dédiés.

## Limites de validation locale

L’environnement d’audit courant ne contient pas les dépendances PHP (`vendor/bin/phpunit` absent) et ne fournit pas la commande PHP. Les tests backend et le lint PHP sont donc à exécuter dans la CI ou dans l’environnement de développement du projet. Les dépendances frontend ne sont pas installées localement non plus ; les tests Jest et le lint Next.js restent à exécuter dans la CI.

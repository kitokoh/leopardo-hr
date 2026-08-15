# Mini-spec — Issue #3708

## Intention
Rendre les gardes d’hygiène du dépôt réellement bloquantes dans GitHub Actions afin d’empêcher les dérives silencieuses de version, de variables d’environnement et de domaines first-party.

## Périmètre
La solution ajoute `dev-hub/tools/check-canonical-domains.sh`, conserve les gardes existantes `check-app-version-sync.sh` et `check-env-example-parity.sh`, puis les exécute dans le job indépendant `hygiene-guards` du workflow `.github/workflows/architecture-check.yml`. Le workflow est déjà déclenché sur les pull requests, les pushes sur `main` et les merge groups.

La garde de domaines inspecte uniquement les fichiers texte suivis par Git et les hôtes first-party du registre explicite. Elle échoue avec une erreur GitHub Actions lorsqu’un nouvel hôte appartenant aux suffixes Leopardo, Render ou Vercel apparaît sans être enregistré. Les domaines SaaS tiers ne sont pas concernés.

## Critères d’acceptation

| Critère | Vérification |
|---|---|
| APP_VERSION synchronisée | `bash dev-hub/tools/check-app-version-sync.sh api` retourne 0 |
| Parité `.env.example` | `bash dev-hub/tools/check-env-example-parity.sh api` retourne 0 |
| Domaines contrôlés | `bash dev-hub/tools/check-canonical-domains.sh` retourne 0 sur l’arbre actuel |
| CI bloquante | Le job `hygiene-guards` n’utilise pas `continue-on-error` |
| Déclencheurs | Le job hérite des déclencheurs PR, push `main` et `merge_group` du workflow |
| Robustesse shell | Les scripts passent `bash -n` et utilisent `set -euo pipefail` |

## Validation

Les trois gardes et la validation syntaxique Bash ont été exécutées localement avec succès. La parité actuelle rapporte 273 clés documentées et aucune clé manquante.

## Hors périmètre

La décision et la mise en production du domaine API canonique restent couvertes par l’issue d’infrastructure dédiée. Cette issue installe uniquement la barrière CI contre la réapparition de domaines first-party non enregistrés.

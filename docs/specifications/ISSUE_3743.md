# Mini-spec — Issue #3743

## Intention
Éviter que chaque push sur `main` déclenche deux matrices de builds mobiles staging pour les mêmes quatre applications.

## Décision
`mobile-distribute-main.yml` est le workflow maître des pushes sur `main`, avec son checkout de `github.sha` protégé. `mobile-distribute.yml` conserve uniquement les distributions déclenchées par tags `v*-staging`/`v*-prod` et les lancements manuels. Ses builds ne sont donc plus concurrents sur un push `main`.

## Critères d’acceptation

| Déclencheur | Workflow responsable |
|---|---|
| Push `main` avec changements mobiles | `mobile-distribute-main.yml` uniquement |
| Tag `v*-staging` ou `v*-prod` | `mobile-distribute.yml` |
| Lancement manuel | `mobile-distribute.yml` ou `mobile-distribute-main.yml` selon le besoin |

Le diff ne modifie ni les matrices, ni les secrets Firebase, ni les étapes de build et de distribution.

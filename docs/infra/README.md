# Infrastructure - statut documentaire

Ce dossier regroupe les documents d'infrastructure utiles a la comprehension et au runbook, sans en faire des sources de verite canoniques par defaut.

## Statut des documents

| Fichier | Statut | Usage autorise | Source canonique qui prime |
|---|---|---|---|
| `Leopardo_RH_Architecture_Deploiement.pdf` | Reference d'architecture non canonique | Comprendre l'architecture cible, les flux, les services et l'historique de deploiement | `PILOTAGE.md`, `CHANGELOG.md`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `docs/GESTION_PROJET/RENDER_SETUP.md` |

## Regle

Ce PDF ne doit pas etre utilise seul pour piloter une decision infra, car il melange :

- l'etat courant MVP/GO,
- des hypotheses de migration futures,
- et des choix d'hebergement qui peuvent evoluer plus vite que le PDF.

Toute contradiction est tranchee en faveur :

1. de `PILOTAGE.md`,
2. du `CHANGELOG.md`,
3. des runbooks de `docs/GESTION_PROJET/`.
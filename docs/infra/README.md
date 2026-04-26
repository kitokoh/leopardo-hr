# Infrastructure - statut documentaire

Ce dossier regroupe les documents d'infrastructure utiles a la comprehension et au runbook.

Pour l'etat courant, la reference principale est desormais le document markdown :

- `ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`

Le PDF historique reste conserve comme archive de vision et de projection.

## Statut des documents

| Fichier | Statut | Usage autorise | Source canonique qui prime |
|---|---|---|---|
| `ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md` | Reference principale etat courant | Comprendre l'etat de deploiement reel API/Web/healthcheck au 25 avril 2026 | `PILOTAGE.md`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `docs/GESTION_PROJET/RENDER_SETUP.md` |
| `Leopardo_RH_Architecture_Deploiement.pdf` | Archive de vision / projection | Comprendre l'architecture cible, les flux, les services et l'historique de deploiement sans piloter l'operationnel courant | `ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`, `PILOTAGE.md`, `CHANGELOG.md`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `docs/GESTION_PROJET/RENDER_SETUP.md` |
| `ALIGNEMENT_PDF_REALITE_2026-04-25.md` | Note d'alignement operationnelle | Lister les ecarts entre le PDF et l'etat reel du depot, et trancher ce qui fait foi aujourd'hui | `PILOTAGE.md`, `CHANGELOG.md`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` |

## Regle

Le document a utiliser en premier pour decrire l'infrastructure actuelle est :

- `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`

Le PDF ne doit pas etre utilise seul pour piloter une decision infra, car il melange :

- l'etat courant MVP/GO,
- des hypotheses de migration futures,
- et des choix d'hebergement qui peuvent evoluer plus vite que le PDF.

Toute contradiction est tranchee en faveur :

1. de `PILOTAGE.md`,
2. du `CHANGELOG.md`,
3. des runbooks de `docs/GESTION_PROJET/`.

## Lecture recommandee

Pour repondre a la question "est-ce que le PDF correspond encore a notre realite ?", lire dans cet ordre :

1. `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
2. `docs/infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md`
3. `PILOTAGE.md`
4. `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
5. `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
6. `docs/GESTION_PROJET/TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md`

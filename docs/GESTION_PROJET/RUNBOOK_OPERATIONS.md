# RUNBOOK - OPERATIONS

## Objectif

Donner le point d'entree unique pour les procedures d'exploitation Leopardo RH. Ce document ne remplace pas les runbooks specialises ; il indique quel runbook utiliser selon l'incident ou l'action.

## Runbooks sources de verite

| Situation | Runbook |
|---|---|
| Deploiement staging/production | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` |
| Rollback applicatif | `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md` |
| Incident P1 | `docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md` |
| Observabilite Sentry | `docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md` |
| Backup et restore PostgreSQL | `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` |
| Journal des drills | `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` |
| Tests locaux | `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` |

## Sequence incident

1. Qualifier impact : API indisponible, fuite potentielle, paie bloquee, pointage bloque, degradation mineure.
2. Lire le dernier SHA deploye et les checks GitHub Actions associes.
3. Si indisponibilite production : appliquer `RUNBOOK_INCIDENT_P1.md`.
4. Si regression post-deploy : appliquer `RUNBOOK_ROLLBACK.md`.
5. Si corruption ou perte de donnees suspectee : appliquer `RUNBOOK_BACKUP_RESTORE.md` et ouvrir un drill horodate.
6. Documenter la cause, le correctif, les checks et les actions preventives dans le changelog ou le runbook concerne.

## Invariants

- `main` distant reste la source de verite.
- Un deploy se raisonne par SHA, pas seulement par nom de branche.
- Ne pas ignorer un rouge GitHub Actions requis.
- Le statut externe Vercel peut etre non bloquant uniquement quand il echoue sans logs applicatifs et que les GitHub Actions requis sont verts.

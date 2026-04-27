# Gestion projet - Leopardo RH

Ce dossier centralise les documents de pilotage, les runbooks, les audits d'ecarts et les supports d'execution du projet.

## Ordre de lecture recommande

1. `../../PILOTAGE.md` - source de verite operationnelle
2. `PROCHAINES_ACTIONS_MAIN_2026-04-27.md` - backlog operatif court et ordre de reprise pour le prochain dev
3. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` - ecart entre documentation cible et etat reel de `main`
4. `GARDE_FOUS.md` - regles anti-derive
5. `RUNBOOK_DEPLOY.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_INCIDENT_P1.md` - socle d'exploitation
6. `PLAN_ACTION_AMELIORATION.md` - plan d'amelioration backend, conserve comme archive d'execution et feuille de detail

## Sous-ensembles logiques

- `RUNBOOK_*` : exploitation et incidents
- `RAPPORT_*` : constats et comptes rendus
- `SCENARIOS_TEST_*` : verification manuelle ou CI
- `PROCHAINES_ACTIONS_MAIN_*.md` : point d'entree canonique "quoi faire ensuite"
- `PLAN_ACTION_AMELIORATION.md` : backlog d'amelioration technique structure
- `TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md` : derive et rattrapage de la documentation infra

## Regle

Les fichiers de ce dossier doivent toujours expliciter s'ils sont :

- un document canonique de pilotage,
- un runbook executable,
- un audit ponctuel,
- ou une archive de contexte.

Le document de reprise pour le prochain developpeur doit rester court,
date, et concret : priorites, prerequis, definition du fini et liens canoniques.

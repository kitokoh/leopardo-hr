# Gestion projet - Leopardo RH

Ce dossier centralise les documents de pilotage, les runbooks, les audits d'ecarts et les supports d'execution du projet.

## Ordre de lecture recommande

1. `../../PILOTAGE.md` - source de verite operationnelle
2. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` - ecart entre documentation cible et etat reel de `main`
3. `GARDE_FOUS.md` - regles anti-derive
4. `RUNBOOK_DEPLOY.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_INCIDENT_P1.md` - socle d'exploitation
5. `PLAN_ACTION_AMELIORATION.md` - plan d'amelioration backend, conserve comme archive d'execution et feuille de detail

## Sous-ensembles logiques

- `RUNBOOK_*` : exploitation et incidents
- `RAPPORT_*` : constats et comptes rendus
- `SCENARIOS_TEST_*` : verification manuelle ou CI
- `PLAN_ACTION_AMELIORATION.md` : backlog d'amelioration technique structure
- `TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md` : derive et rattrapage de la documentation infra

## Regle

Les fichiers de ce dossier doivent toujours expliciter s'ils sont :

- un document canonique de pilotage,
- un runbook executable,
- un audit ponctuel,
- ou une archive de contexte.

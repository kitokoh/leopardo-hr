# Supervision GitHub Project et multi-agents

Version: 1.0  
Date: 2026-06-13

## Objectif

Ce fichier explique comment transformer `03_GITHUB_PROJECT_IMPORT.csv` en pilotage multi-agents sans perdre la vision produit.

Chaque ticket PA2 doit pouvoir etre assigne a un agent, implemente en PR, verifie par CI et relu contre son critere d'acceptation.

## Champs GitHub Project recommandes

- `Ticket ID`
- `Priority`
- `Area`
- `Surface`
- `Status`
- `Owner`
- `Dependencies`
- `Acceptance Criteria`
- `Risk`
- `Release`
- `PR`
- `Validation Evidence`

## Regles de PR

Une PR PA2 doit:

- nommer le ticket dans le titre ou la description;
- expliquer les surfaces touchees;
- mettre a jour `CHANGELOG.md`;
- mettre a jour la matrice frontend/API si un bouton ou endpoint change;
- fournir preuve CI GitHub Actions ou raison de skip;
- eviter de melanger refonte UI, migration DB et changement API sans justification.

## Supervision attendue

Le lead agent verifie regulierement:

1. que les branches sont a jour avec `origin/main`;
2. que les PR ne dupliquent pas un ticket deja en cours;
3. que les modifications respectent `AGENTS.md`;
4. que les nouvelles routes sont documentees;
5. que les apps mobiles ne reintroduisent pas de blocage startup;
6. que les devises/pays/langues ne sont pas recodees en dur;
7. que les jobs/notifications restent asynchrones et auditables.

## Automation a construire

- Script de validation du CSV: colonnes, ID uniques, dependances existantes.
- Script de generation d'issues depuis CSV.
- Action GitHub qui refuse une PR sans ID PA2 quand elle touche code produit.
- **Fait (PA2-AUTO-005)** : rapport hebdomadaire des tickets ouverts, bloques, merges et a verifier — `dev-hub/tools/plan-action2-weekly-report.sh` (merges PA2-* des N derniers jours, PR PA2-* stale ou avec CI en echec, tickets P0 sans PR ni issue assignee), execute chaque lundi par `.github/workflows/plan-action2-weekly-report.yml` (publie dans le step summary de l'action ; poste aussi un commentaire sur une issue de suivi si `vars.PLAN_ACTION2_WEEKLY_REPORT_ISSUE` est configuree).


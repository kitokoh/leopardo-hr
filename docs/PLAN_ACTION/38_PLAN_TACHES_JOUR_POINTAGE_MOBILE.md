# Plan 38 - Taches du jour et pointage mobile

## Objectif

Fermer la boucle metier entre manager et employe : le manager assigne les taches du jour, l'employe les voit dans son pointage et peut declarer leur realisation avec duree reelle.

## Livrables

- API tasks : validation tenant-scope des `assigned_to.*` sur creation et mise a jour.
- API docs : contrat `/tasks/today`, champs execution (`estimated_minutes`, `completed_minutes`, `completion_note`, `performance_score`, `template_key`, `recurrence_rule`).
- Mobile manager : ecran `/tasks` pour lister les taches du jour et assigner une tache a un collaborateur.
- Mobile manager : templates metier simples pour agriculture, maintenance, commerce et logistique.
- Mobile employee : bouton `Terminer` sur chaque tache du jour, saisie temps reel + note, puis PATCH `/tasks/{task}`.
- Tests backend : refus d'assignation cross-tenant et completion employee avec score performance.

## Critere de sortie

Le pointage n'est plus isole : il devient un point d'entree operationnel vers le travail du jour et la mesure de performance terrain.

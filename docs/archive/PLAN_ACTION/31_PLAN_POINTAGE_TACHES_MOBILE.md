# Plan 31 - Pointage multi-evenements et taches terrain

## Objectif

Transformer le pointage mobile employee en workflow terrain exploitable pour les tests marketing :

- plusieurs sessions de pointage dans une meme journee ;
- typage metier du pointage : normal, heures supplementaires, pause, reprise, mission, deplacement, formation, autre ;
- resume quotidien lisible avec sessions, pauses, heures et heures supplementaires ;
- taches du jour assignees apres pointage ;
- duree prevue/reelle, note de realisation et score de performance sur les taches ;
- compatibilite API mobile sans casser les anciens appels `check-in` / `check-out`.

## Livrable implemente

- `attendance_logs` accepte maintenant `work_type`, `punch_note` et `punch_meta`.
- `AttendanceService` cree `session_number = 2+` apres une session fermee et bloque seulement le double pointage quand une session est encore ouverte.
- `GET /api/v1/attendance/today` renvoie aussi `sessions` et `summary`.
- `GET /api/v1/attendance` expose les champs multi-session.
- Le mobile employee propose le type de pointage avant l'appel API.
- `Mon mois complet` utilise un appel avec timeout pour eviter le spinner infini.
- `tasks` expose les champs d'execution : duree prevue, duree realisee, note, recurrence, template et score.
- `GET /api/v1/tasks/today` renvoie les taches du jour assignees a l'employe.

## Prochaines extensions

- Ecran manager complet pour creer des templates par metier et assigner des taches recurrentes.
- Ecran employee dedie aux details de journee et validation des taches avant depart.
- Agregation performance dans le profil carriere de l'utilisateur.
- Tests E2E mobile/API avec vrais comptes demo staging.

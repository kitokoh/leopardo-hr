# Plan 49 - Details journaliers du pointage employee

## Objectif

Rendre le pointage multi-session comprehensible pour un employe sans casser le
workflow existant de correction via les trois points.

## Probleme traite

Le backend expose maintenant plusieurs sessions par jour, mais l'ecran mobile
employee ne donnait pas encore une vue claire des pointages successifs, pauses,
heures supplementaires et duree reelle d'une journee.

## Livrables realises

- Les trois points d'une ligne de semaine ouvrent une bottom sheet d'actions.
- L'action `Details de la journee` affiche :
  - toutes les sessions du jour ;
  - le type de pointage (`normal`, `overtime`, `break`, `resume`, `mission`,
    `travel`, etc.) ;
  - le temps travaille ;
  - les pauses estimees entre deux sessions ;
  - les heures supplementaires ;
  - le gain estime.
- L'action de correction reste disponible depuis le meme menu :
  - employe : `Demander une modification` ;
  - manager/RH si reutilise plus tard : `Modifier`.

## Validation

- `dart format front/mobile_apps/leopardo_employee/lib/features/attendance/screens/attendance_screen.dart`
- `git diff --check`
- `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-workflow-contracts.ps1`

## Suite logique

- Ajouter un test widget cible sur l'ouverture du menu jour et le rendu des
  sessions multiples.
- Brancher les taches realisees dans la vue details lorsque l'API expose un
  historique par date et pas seulement `GET /tasks/today`.

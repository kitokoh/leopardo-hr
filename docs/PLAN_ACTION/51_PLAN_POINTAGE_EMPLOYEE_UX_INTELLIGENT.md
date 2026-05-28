# Plan 51 - Pointage employee UX intelligent

Date : 2026-05-28

## Objectif

Rendre le pointage mobile employee plus naturel sans casser le socle multi-sessions deja disponible cote API.

## Decisions livrees

- Premier clic de la journee : arrivee normale directe, sans bottom sheet.
- Premier depart de la journee : depart direct, sans question inutile.
- Les choix avances restent disponibles uniquement quand la journee est deja segmentee :
  - pause,
  - reprise,
  - heures supplementaires,
  - mission,
  - deplacement.
- Le contrat backend existant reste inchange : `work_type` continue de piloter les types de sessions.

## Verification

- Validation locale ciblee : format Dart sur l'ecran de pointage employee.
- Garde attendu en CI : `Analyze leopardo_employee` et `Build Debug leopardo_employee`.

## Suite

- Ajouter un test widget mobile cible pour verifier que la premiere arrivee et le premier depart n'ouvrent pas de bottom sheet.
- Enrichir ensuite les donnees manager pour afficher les demandes d'avance/absence avec un contexte lisible.

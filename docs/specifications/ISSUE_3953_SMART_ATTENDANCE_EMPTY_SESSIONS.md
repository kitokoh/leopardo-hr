# Issue #3953 — Smart Attendance : `firstWhere` sur liste vide → StateError

## Problème

`ActiveGeoSessionNotifier.loadSessions()` appelait
`sessions.firstWhere((s) => s.isActive, orElse: () => sessions.isEmpty ? sessions.first : sessions.first)`.
Les deux branches de l'orElse renvoyaient `sessions.first` : sur liste vide
(employé sans session GPS), `firstWhere` levait `StateError` → le `catch`
affichait « Impossible de charger les sessions GPS » pour TOUT employé sans
session, masquant l'état légitime « aucune session ».

## Correctif

Garde `sessions.isEmpty ? null : firstWhere(...)` :
- liste vide → `active = null` → `activeSession = null` → `clearActive=true`
- liste non vide → comportement inchangé

## Critères de succès

1. `flutter analyze` leopardo_employee : 0 erreur.
2. Un employé sans session charge l'écran sans erreur (recentSessions vide,
   activeSession null).

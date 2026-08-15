# Feature Specification: Écrans orphelins GoRouter supprimés (Closes #3812)

**Feature Branch**: `fix/3812-orphan-screens-cleanup`
**Issue**: #3812 (MOBILE-1, P3, cleanup)

## Contexte

19 écrans listés par l'audit n'ont aucune route GoRouter ni `context.go/push`. Re-scan sur main :
certains ont été routés depuis (#3826 : HR evaluations/notifications/history) ou supprimés (#2597 :
ai_voice). Restent 13 écrans réellement orphelins, chacun dans un feature-dir auto-référencé
(seule référence externe = registration `core_providers.dart` inutilisée).

## Correctif

Suppression de 11 dossiers features (~40 fichiers) + 10 registrations de providers
(`core_providers.dart`) : employee `ai_chat`, `vehicle_position` ; manager `contracts`,
`expenses`, `modules`, `organigramme`, `training` ; hr `ai_chat`, `approvals`, `expenses`,
`modules`, `training`, `vehicle_position`. Conservés : écrans routés (manager ai-chat/
approvals/vehicle, hr contracts/organigramme) et features des PRs #2597/#3826.

## Critères de succès

1. `grep features/<feat>/` → 0 référence pour chaque feature supprimée.
2. `check-mobile-manifest-routes.sh` → OK (aucune route supprimée du manifeste).
3. `flutter analyze` ×3 apps → 0 erreur (CI).

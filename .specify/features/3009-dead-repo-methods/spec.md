# Feature Specification: Méthodes mortes des repositories mobiles retirées (Closes #3009)

**Feature Branch**: `fix/3009-dead-repo-methods`
**Issue**: #3009 (T169, P3, mobile)

## Contexte

Méthodes de repository jamais appelées (hors #2769/#2763). Re-scan sur main après #3812 :
les méthodes des features supprimées (getPosition, getDepartmentHierarchy) sont couvertes par
#3812. Restent 6 méthodes ×3 apps + 3 fichiers `push_notification_repository.dart` entiers.

## Correctif

- `getMyQuickEstimate` (attendance_repository ×3), `updateFolder`/`deleteFolder`
  (cabinet_repository ×3), `getProjects`/`getMyTasks` (project_repository ×3),
  `getCompanyRequests` (user_auth_repository ×3) — blocs méthode retirés.
- `push_notification_repository.dart` ×3 supprimés (aucune référence externe).

## Critères de succès

1. `rg .getMyQuickEstimate|.updateFolder|...` → 0 référence ;
2. accolades équilibrées dans les fichiers édités ;
3. `flutter analyze` ×3 apps vert (CI).

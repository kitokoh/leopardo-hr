# ISSUE_3009 — Méthodes mortes dans les 3 apps tenant

**Statut**: Fixed (PR `fix/3009-dead-repo-methods`) · **Priorité**: P3 · **Module**: mobile (3 apps)

## Correctif

18 méthodes jamais appelées retirées (getMyQuickEstimate, updateFolder, deleteFolder,
getProjects, getMyTasks, getCompanyRequests — ×3 apps) + `push_notification_repository.dart`
entier ×3 (le push passe par ApiClient). getPosition/getDepartmentHierarchy couverts par #3812.

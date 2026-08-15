# ISSUE_3826 — Manifest #2212 rouge : routes HR retirées par #3715 mais toujours servies

**Statut**: Fixed (PR `fix/3826-manifest-hr-routes`) · **Priorité**: P2 · **Module**: mobile-hr

## Constat

`check-mobile-manifest-routes.sh` échoue sur main : `/notifications`, `/evaluations`, `/history`
servis par `MobileExperienceService.php` (manifeste) mais absents du routeur GoRouter
`leopardo_hr/lib/app.dart` depuis #3715 (Closes #3284). Crash GoRouter au tap.

## Correctif

Imports + GoRoutes restaurés (`NotificationListScreen`, `EvaluationListScreen`, `HistoryScreen`)
— les écrans et repos existaient toujours. Les 6 routes réellement mortes ne sont pas restaurées.
Garde #2212 → PASS.

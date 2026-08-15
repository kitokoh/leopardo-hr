## Plan technique
1. `AnalyticsView.vue` : brancher sur `/admin/dashboard/stats`, `/admin/dashboard/activities`, `/admin/dashboard/alerts` (vérifier shapes via `PlatformAdminDashboardController`).
2. `SystemView.vue` : identifier les endpoints plateforme réels disponibles (observability, queue health…) ; pour chaque brique : API réelle ou état vide honnête.
3. Supprimer données factices + setTimeout simulés.
4. Lint + build. CHANGELOG.

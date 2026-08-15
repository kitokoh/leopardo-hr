## Plan technique
1. `src/views/exports/ExportsView.vue:172` : `api.get('/v1/hr-reports', ...)` → `api.get('/admin/hr-reports', ...)`.
2. `src/views/marketing/MarketingOAuthView.vue:118` : `api.put('/v1/platform/marketing/oauth-config', ...)` → `api.put('/admin/platform/marketing/oauth-config', ...)`.
3. Grep de contrôle : plus aucune occurrence de `v1/hr-reports` / `v1/platform/marketing/oauth-config` dans `src/`.
4. `npm run lint` + `npm run build` dans `front/admin-dashboard`.
5. CHANGELOG + PR `fix/2237-admin-dead-urls` (`Closes #2237`).

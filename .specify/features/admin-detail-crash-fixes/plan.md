## Plan technique
1. `src/layouts/DashboardLayout.vue` : ajouter `import router from '@/router'` ; remplacer `routes.find(...)` par `router.options.routes.find(...)` dans le computed `breadcrumbs`.
2. `src/views/companies/CompanyDetailView.vue` : réécrire `scoreColor` avec `const score = health.value?.adoption?.health_score ?? 0`.
3. Lint (`npm run lint`) + build (`npm run build`) de `front/admin-dashboard`.
4. CHANGELOG.md + PR.

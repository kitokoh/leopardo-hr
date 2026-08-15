## Plan technique
1. `front/admin-dashboard/vite.config.js` : `base: './'` → `base: '/'`.
2. Créer `front/admin-dashboard/public/_redirects` : `/* /index.html 200`.
3. `npm run build` : vérifier que `dist/index.html` référence `/assets/*`.
4. CHANGELOG.md + PR.

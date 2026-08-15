# Feature Specification: Admin SPA — base './' + createWebHistory → page blanche (hard refresh / deep link)

**Feature Branch**: `fix/2334-admin-vite-base-root`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2334
**Découvert lors du test de la plateforme** : 2026-08-14

## Problème

Sur https://leo-admin.pages.dev, tout hard refresh ou accès direct à une sous-route (ex. `/companies`) charge `index.html` comme JS → page blanche. Vérifié : `/companies/assets/*.js` retourne text/html.

**Cause racine** : `front/admin-dashboard/vite.config.js` a `base: './'` (chemins relatifs) combiné à `createWebHistory()` : sur une sous-route, les assets relatifs se résolvent sous le chemin courant (`/companies/assets/...`).

## User Stories & Testing

### User Story 1 — Assets absolus (P1)
**Acceptance Scenarios**:
1. Given le build vite, When inspection du `dist/index.html`, Then les assets référencés commencent par `/assets/` (base racine).
2. Given le déploiement sur leo-admin.pages.dev, When hard refresh sur `/companies`, Then le JS est servi avec le bon Content-Type (pas text/html).

### User Story 2 — Deep links SPA (P2)
**Acceptance Scenarios**:
1. Given un accès direct à `/companies` (pas de routage côté serveur), When Cloudflare Pages sert la route, Then `index.html` est renvoyé (fallback SPA) et le router rend la vue.
2. Given `public/_redirects`, When déploiement Pages, Then la règle de fallback SPA est active.

## Plan technique
1. `vite.config.js` : `base: './'` → `base: '/'`.
2. Ajouter `public/_redirects` avec le fallback SPA Cloudflare Pages : `/* /index.html 200`.
3. Garder `createWebHistory()`. Vérifier build + lint.
4. CHANGELOG + PR `fix/2334-...` `Closes #2334`, CI verte.

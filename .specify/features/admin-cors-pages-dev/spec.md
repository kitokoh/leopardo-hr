# Feature Specification: CORS — leo-admin.pages.dev absent de la allowlist

**Feature Branch**: `fix/2333-admin-cors-pages-dev`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2333
**Découvert lors du test de la plateforme** : 2026-08-14

## Problème

Le panneau admin déployé sur https://leo-admin.pages.dev ne peut pas appeler l'API : aucune en-tête `Access-Control-Allow-Origin` pour cette origine (curl-prouvé : ACAO présent pour admin.leopardo-rh.com, absent pour leo-admin.pages.dev). Toutes les requêtes navigateur échouent avec « Erreur de connexion ».

**Cause racine** : `api/config/cors.php` whiteliste en dur `https://admin.leopardo-rh.com` et `env('ADMIN_DASHBOARD_URL')` — variable non renseignée sur Render ; l'origine réelle du panneau (`leo-admin.pages.dev`) n'est pas listée.

## User Stories & Testing

### User Story 1 — L'origine Pages est autorisée (P1)
**Acceptance Scenarios**:
1. Given une requête OPTIONS/GET depuis l'origine `https://leo-admin.pages.dev`, When le middleware CORS traite, Then `Access-Control-Allow-Origin` = `https://leo-admin.pages.dev` et `Access-Control-Allow-Credentials: true`.
2. Given n'importe quelle preview Cloudflare (`https://*.pages.dev`), When requête CORS, Then l'origine est autorisée via pattern.
3. Given une origine inconnue, When requête, Then aucun header ACAO (allowlist explicite conservée, jamais `*`).

### User Story 2 — Non-régression audit API 2026-07-19 (P1)
**Acceptance Scenarios**:
1. Given `supports_credentials=true`, When test `CorsAndTrustedProxyTest`, Then vert (jamais `*` dans allowed_origins).

## Plan technique
1. `api/config/cors.php` : ajouter `https://leo-admin.pages.dev` à `allowed_origins` et `https://*.pages.dev` à `allowed_origins_patterns`.
2. Étendre `api/tests/Feature/Security/CorsAndTrustedProxyTest.php` avec un test de non-régression (origin Pages autorisée, wildcard interdit).
3. CHANGELOG + PR `fix/2333-...` `Closes #2333`, CI verte.

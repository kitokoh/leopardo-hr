# Feature Specification: QA 360° Audit Wave — 2026-08-15 (expert session)

**Feature Dir**: `.specify/features/qa-360-audit-expert-2026-08-15`
**Created**: 2026-08-15 | **Status**: Draft → In progress
**Base**: `origin/main` @ 1d512cc3

## Problème

Audit 360° (API, vitrine, admin, mobile, kiosk, edge, CI) : 23 manquements nouveaux
identifiés et vérifiés (voir findings-registry.md), dé-dupliqués contre les 51 issues
ouvertes et les PRs récentes. Chaque manquement → issue GitHub titrée `T###: ...` via
le protocole Spec Kit, puis implémentation en PRs unitaires `Closes #<issue>`.

## User Stories & Testing

### US1 — Sécuriser l'authentification & l'API (A-01 → A-04)
**Acceptance Scenarios**:
1. Given un email Google sans employé existant, When login OAuth, Then 401 `UNKNOWN_ACCOUNT` (pas de création de compte `ordinary` actif), sauf si feature gate explicite.
2. Given une erreur interne (SQL/Redis/domaine) sur un endpoint, When réponse, Then body = code d'erreur stable, jamais `$e->getMessage()`.
3. Given deux imports CSV concurrents avec emails dupliqués, When import, Then 422 par ligne (pas 500).
4. Given une requête sur un modèle `BelongsToCompany` sans compagnie courante, When exécution, Then échec explicite (403/exception) plutôt que requête cross-tenant silencieuse.

### US2 — Fiabiliser la vitrine web (W-01 → W-08)
**Acceptance Scenarios**:
1. Given les specs e2e navigation, When run, Then chaque assertion cible un lien réel (plus de garde `isVisible()` silencieuse).
2. Given un GET authentifié (`/dashboard`, `/payroll`, `/employees`), When service worker intercepte, Then pas de mise en cache du HTML privé.
3. Given `/mobile` avec `?lang=en`, When rendu, Then navbar ET body en anglais (locale unique partagée).
4. Given un partage de page guide sur WhatsApp, When preview, Then titre + description + image OG présents.
5. Given la FAQ, When audit a11y, Then input labelé, accordéons aria-expanded, drawer aria-label localisé.
6. Given la Navbar, When changement de langue, Then l'URL porte `?lang=` (sync router).

### US3 — Admin dashboard (AD-01 → AD-04)
**Acceptance Scenarios**:
1. Given un clic « marquer lu », When API, Then PATCH/POST (200), l'état persiste, plus de 405.
2. Given la command palette, When sélection d'une route `requiresTenant` non débloquée, Then entrée absente/filtrée (pas de toast + redirect).
3. Given `document.title`, When navigation, Then clés i18n résolues (jamais de littéral `marketing.oauth.nav_title`).
4. Given un échec de `/admin/fleet/alerts`, When chargement, Then bannière d'erreur visible (pas de liste vide silencieuse).

### US4 — Mobile, kiosk, edge, CI (M-01 → M-07)
**Acceptance Scenarios**:
1. Given un push main avec l'app HR dans la matrice de distribution, When workflow staging, Then FIREBASE_APP_ID correct → distribution réussie (validation node verte).
2. Given une installation edge client, When install.sh, Then Caddyfile.edge présent et edge-proxy démarre.
3. Given le cron branch-protection-guard, When exécution, Then plus de 403 (permissions adaptées ou retrait du check admin-only).
4. Given les routes GoRouter manager, When parsing, Then aucune route dupliquée.
5. Given un push main, When workflows mobiles, Then un seul build staging par app (pas de double upload).
6. Given kiosk.db, When création, Then permissions 0600 ; Given un POST /local/punch, Then body borné + rate-limit local.

## Plan technique (résumé)
- API : garde lookup Google OAuth + feature gate ; helper d'erreur standardisé ; catch 23505 par ligne import ; fail-closed scope BelongsToCompany.
- Web : specs e2e réalignées ; SW exclut routes privées + cleanup interval ; locale partagée /mobile ; OG guides ; a11y FAQ ; suppression seo-metadata.ts ; footer {label,href} ; sync `?lang=` Navbar.
- Admin : PATCH/POST notifications ; filtrage palette ; titre résolu ; bannière FleetView.
- Mobile/CI/Edge : fix ternaire App ID HR ; install.sh télécharge Caddyfile.edge ; garde branch-protection ; dédup routes manager ; dédup workflows mobiles ; perms kiosk.db ; bornes bridge.

## Dépendances
- Indépendantes les unes des autres (fichiers disjoints) → exécution parallèle, PR unitaires.
- Ordre conseillé : T017 (CI P1), T001/T013 (comportement), puis P2, puis P3.

## Critères de succès
- 100 % des T### couverts par une issue GitHub ; chaque fix en PR `Closes #<issue>` + entrée CHANGELOG ;
- vérifications locales vertes (lint/tsc/jest/build) pour le frontend ; CI verte sur les PRs backend ;
- main reste vert à tout moment.

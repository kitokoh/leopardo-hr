# Feature Specification: Détail compagnie/user — crash JS (routes non défini + healthScore non défini)

**Feature Branch**: `fix/2335-admin-detail-crash`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2335
**Découvert lors du test de la plateforme** : 2026-08-14

## Problème

Les pages de détail (ex. `/companies/{id}`) ne rendent que le H1 ; crash JS :

1. `DashboardLayout.vue` — le computed `breadcrumbs` utilise `routes.find(...)` mais `routes` n'est ni importé ni défini (seul `useRoute()` est importé). La variable `routes` n'existe que dans le module router (`src/router/index.js`, pas exportée).
2. `CompanyDetailView.vue` — le computed `scoreColor` lit `healthScore.value` (variable inexistante) ; la donnée réelle est `health.adoption.health_score`.

## User Stories & Testing

### User Story 1 — La navigation de détail rend sans crash (P1)
**Acceptance Scenarios**:
1. Given une route avec `meta.parent` (ex. `/companies/{id}`), When le layout se monte, Then le breadcrumb parent est résolu sans ReferenceError (`routes is not defined`).
2. Given la vue détail compagnie chargée, When `scoreColor` est évalué, Then la couleur provient de `health.adoption.health_score` (accès défensif si données partielles).

### User Story 2 — Accès défensif aux données santé (P1)
**Acceptance Scenarios**:
1. Given une réponse health sans bloc `adoption`, When le computed s'évalue, Then aucun crash (score par défaut 0 → rouge).
2. Given une réponse health complète, When affichage, Then le score /100 est rendu avec la couleur attendue (≥75 vert, ≥50 jaune, sinon rouge).

## Plan technique
1. `DashboardLayout.vue` : importer le router (`import router from '@/router'`) et résoudre les routes parent via `router.options.routes`.
2. `CompanyDetailView.vue` : remplacer `healthScore.value` par une lecture défensive `health.value?.adoption?.health_score ?? 0`.
3. Vérifier lint + build admin, navigation de détail.
4. CHANGELOG + PR `fix/2335-...` `Closes #2335`, CI verte.

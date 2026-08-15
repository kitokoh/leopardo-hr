# Feature Specification: Gestion utilisateurs plateforme (API + UsersView réels)

**Feature Branch**: `feat/2269-platform-users`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2269

## Contexte
`UsersView.vue` est 100 % mock (150 faux utilisateurs `Math.random`), `UserDetailView` crashe (`dashboardStore.users` undefined), aucun endpoint backend de gestion utilisateurs plateforme n'existe.

## User Stories & Testing

### User Story 1 — Le super-admin liste les vrais utilisateurs plateforme (P1)
Le super-admin ouvre « Utilisateurs » : table paginée, recherche nom/email, statut actif, entreprise liée, dernier login.

**Independent Test**: Feature test backend `GET /api/v1/admin/users` (pagination, recherche, tri allowlisté) + navigation manuelle.

**Acceptance Scenarios**:
1. Given un token super-admin, When GET /admin/users, Then 200 + pagination + utilisateurs réels de `public.users`.
2. Given `?search=foo`, When GET /admin/users, Then seuls les utilisateurs dont nom/email contiennent foo.
3. Given un token tenant/employé, When GET /admin/users, Then 401/403 (jamais de fuite).

### User Story 2 — Le super-admin active/désactive un utilisateur (P1)
Un utilisateur peut être désactivé (blocage login) puis réactivé ; l'utilisateur courant ne peut pas se désactiver lui-même.

**Acceptance Scenarios**:
1. Given PATCH /admin/users/{id} `{is_active:false}`, When l'utilisateur tente login, Then refusé.
2. Given PATCH sur soi-même `{is_active:false}`, Then 422.
3. Given PATCH sur un id inexistant, Then 404.

### User Story 3 — L'UI est réelle (P1)
UsersView + UserDetailView affichent des données API (loading/empty/error), plus de `Math.random` ; `/users/:id` ne crashe pas.

**Acceptance Scenarios**:
1. Given la vue ouverte, When les données chargent, Then spinner → table réelle.
2. Given un id inconnu, When /users/:id, Then état erreur propre (pas de TypeError).
3. Given aucun utilisateur, When la vue s'ouvre, Then état vide explicite.

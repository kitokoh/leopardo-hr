# ISSUE_3006 — Marketing : aucune authentification → 401 systématique

**Statut**: Fixed (PR `fix/3006-marketing-auth`) · **Priorité**: P2 · **Module**: mobile-marketing

## Constat

`front/mobile_apps/leopardo_marketing/lib/main.dart` : pas de login, pas de redirect,
`_bootstrap` vide ; `/marketing/posts*` exige `auth:sanctum` + `api.manager:marketing,principal`
→ 401 systématique, `onUnauthorized` non câblé. App inutilisable hors mock.

## Correctif

Flux d'authentification complet (pattern leopardo_manager/leopardo_hr) :
repository (login `/auth/login` + hydrate `/auth/me`, checkAuth, logout), provider riverpod,
écran de connexion, routeur GoRouter avec redirect → `/login` (refreshListenable),
bouton de déconnexion. Réutilisation de `leopardo_core` (ApiClient, SecureStorage,
AppPreferences, Employee).

## Critères d'acceptation

- Session requise avant usage (redirect /login) ;
- plus de 401 en cascade sans guidance ;
- `flutter analyze` marketing → 0 erreur.

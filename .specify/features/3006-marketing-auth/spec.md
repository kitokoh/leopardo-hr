# Feature Specification: App Marketing — authentification câblée (Closes #3006)

**Feature Branch**: `fix/3006-marketing-auth`
**Issue**: #3006 (T166, P2, mobile)

## Contexte

`leopardo_marketing` (skeleton) : pas de login, pas de redirect, `_bootstrap` vide →
`/marketing/posts*` (auth:sanctum + api.manager:marketing,principal) → 401 systématique.

## User Stories

### US1 — Session requise avant usage (P1)

En tant qu'utilisateur marketing, je me connecte avec mon compte manager marketing/principal et j'accède à l'espace — pas de 401 en cascade sans guidance.

**Acceptance Scenarios**:
1. Given l'app démarrée sans session, When rendu, Then redirection vers l'écran de connexion.
2. Given email/mot de passe valides, When submit, Then session établie (`/auth/me` hydraté) et retour à l'accueil.
3. Given identifiants invalides, When submit, Then message d'erreur affiché, pas de crash.
4. Given un clic déconnexion, When logout, Then token purgé et retour au login.

## Requirements

- **FR-001**: repository `features/auth/data/auth_repository.dart` — login (`/auth/login` + `/auth/me`), checkAuth (token), logout (purge locale).
- **FR-002**: provider `authProvider` (StateNotifier, pattern leopardo_manager sans push).
- **FR-003**: écran de connexion email/mot de passe.
- **FR-004**: routeur GoRouter avec `redirect` → `/login` si non authentifié (refreshListenable).
- **FR-005**: bouton de déconnexion sur la home.

## Success Criteria

- `flutter analyze` marketing → 0 erreur ; CI mobile verte ; plus de 401 sans guidance.

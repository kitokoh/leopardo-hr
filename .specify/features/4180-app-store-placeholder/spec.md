# Feature Specification: Invitations de rôle — plus de lien App Store placeholder (Closes #4180)

**Feature Branch**: `fix/4180-app-store-placeholder`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4180 (P3, api)

## Contexte

`RoleInvitationService::getAppDownloadLink()` renvoyait des URLs App Store avec
des identifiants placeholder (`id0000000000`…`id0000000004`) envoyées dans les
e-mails d'invitation de rôle, les `app_links` de `EmployeeResource` et les
réponses du `RoleAssignmentController` → lien mort vers l'App Store.

## User Stories & Testing

### User Story 1 — Un invité ne reçoit jamais de lien mort (P1)

En tant que manager RH invitant un collègue, je veux que l'e-mail ne contienne
que des liens de téléchargement réels.

**Acceptance Scenarios**:
1. Given aucun `IOS_APP_STORE_ID_*` configuré, When e-mail d'invitation, Then le
   lien iOS est omis (null) et le template ne rend que le lien Android — jamais
   `id000000000N`.
2. Given `IOS_APP_STORE_ID_RH=1234567890`, When e-mail rôle rh, Then le lien
   iOS est `https://apps.apple.com/app/Leopardo RH/id1234567890`.
3. Given `EmployeeResource` ou `RoleAssignmentController`, When réponse, Then
   `app_links.ios` est null (pas de placeholder).

## Requirements

- **FR-001**: `config/mobile.php` centralise les IDs App Store iOS (env-driven, null par défaut).
- **FR-002**: `getAppDownloadLink()` renvoie `ios => null` sans identifiant réel configuré.
- **FR-003**: le template `role-assignment.blade.php` ne rend le lien iOS que s'il existe.
- **FR-004**: test du service (3 scénarios).

## Success Criteria

- **SC-001**: `RoleInvitationServiceTest` vert.
- **SC-002**: aucun `id000000000` dans les sorties (grep).
- **SC-003**: PHPStan strict vert, Pint propre.

# ISSUE_4180 — Invitations de rôle : plus de placeholder App Store

> Spec Kit : `.specify/features/4180-app-store-placeholder/spec.md` · Issue : #4180
> Branche : `fix/4180-app-store-placeholder`

## Correctif

- `api/config/mobile.php` : `ios_app_store_ids` par rôle, piloté par
  `IOS_APP_STORE_ID_RH/COMPTABLE/MARKETING/ADMIN/EMPLOYEE` (null par défaut).
- `RoleInvitationService::getAppDownloadLink()` : `ios => null` sans identifiant
  réel configuré (plus jamais `id000000000N`).
- `resources/views/emails/role-assignment.blade.php` : lien iOS rendu
  conditionnellement (`@if !empty`).
- Tests : `RoleInvitationServiceTest` (omission, ID configuré, Android intact).

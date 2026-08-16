# Feature Specification: Password reset — fin de l'oracle de timing multi-schéma (Closes #4495)

**Feature Branch**: `fix/4495-password-reset-oracle`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4495 (P2, api, security)

## Contexte

`PasswordResetController::forgot()` (endpoint PUBLIC non authentifié) résout
l'email via `resolveEmployeeAnywhere()` : lookup indexé `public.user_lookups`
→ si absent, **balayage de TOUS les schémas tenants** (1 `SET search_path` + 1
SELECT par tenant). La réponse générique anti-énumération est contredite par le
canal latéral de temps : email existant = 1 lookup, email inconnu = N allers-
retours. Combiné au contournement rate-limit (#4494), un attaquant peut
énumérer les comptes en mesurant le temps de réponse.

## User Stories & Testing

### User Story 1 — Temps de réponse constant sur le chemin public (P1)

En tant qu'opérateur sécurité, je veux que `forgot()` ne fasse jamais plus
d'un lookup pour décider « compte inconnu », pour que le temps de réponse ne
distingue pas un email existant d'un email inconnu.

**Acceptance Scenarios**:
1. Given un email absent de `public.user_lookups`, When `forgot()` est appelé,
   Then aucune itération `SET search_path` n'est exécutée (0 sweep).
2. Given un email existant (shared ou schéma tenant via lookup), When
   `forgot()` est appelé, Then le jeton est créé et le mail envoyé (comportement inchangé).
3. Given un email inconnu, Then réponse générique identique (anti-énumération
   conservée).

## Requirements

- **FR-001**: `resolveEmployeeAnywhere()` ne balaye plus les schémas tenants
  sur le chemin public — lookup `user_lookups` seul (absent = aucun compte),
  puis `shared_tenants.employees` en dernier recours sans sweep itératif.
- **FR-002**: la méthode `findEmployeeInTenantSchemas()` (sweep) est supprimée.
- **FR-003**: tests : 0 `SET search_path` sur email inconnu (DB::listen) ;
  reset tenant à schéma via lookup toujours vert (test existant adapté).
- **FR-004**: PHPStan strict vert ; CHANGELOG.md mis à jour.

## Success Criteria

- **SC-001**: `forgot()` inconnu = lookup unique, aucune requête itérative par tenant.
- **SC-002**: suite `PasswordResetTest` verte (incl. tenant à schéma).
- **SC-003**: PHPStan strict 0 erreur.

## Hors périmètre

- Le sweep multi-schéma pour les chemins authentifiés/administratifs n'existe
  pas encore — s'il est un jour nécessaire, il sera ajouté hors chemin public.
- Rate-limit du endpoint (#4494) traité séparément.

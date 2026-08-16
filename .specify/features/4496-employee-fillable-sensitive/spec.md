# Feature Specification: Employee — champs sensibles retirés du $fillable (Closes #4496)

**Feature Branch**: `fix/4496-employee-fillable-sensitive`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4496 (P2, api, security)

## Contexte

`Employee::$fillable` contient `password_hash`, `biometric_face_reference_path`,
`biometric_fingerprint_reference_path` et `email_bounced_at`. Les chemins
d'écriture actuels passent par des DTO/request allowlists (pas d'exploit
vivant), mais tout futur `Employee::create($request->all())` ou oubli
d'allowlist écraserait silencieusement le mot de passe ou les références
biométriques — inversé du correctif #3597 (role non-fillable).

## User Stories & Testing

### User Story 1 — Mass-assignment impossible sur les champs sensibles (P1)

En tant qu'opérateur sécurité, je veux que `create()`/`fill()` avec un tableau
contenant les champs sensibles ne puisse jamais les écrire, pour qu'aucun
futur appel non allowlisté ne compromette un compte.

**Acceptance Scenarios**:
1. Given `Employee::create(['password_hash' => 'x'])`, Then le champ n'est pas
   écrit (null/absent en base).
2. Given les services légitimes (création employé, import, invitation, reset,
   biométrie), When ils écrivent ces champs, Then l'écriture passe par
   assignation explicite (hors fillable) et fonctionne toujours.
3. Given le webhook de bounce, When il marque `email_bounced_at`, Then
   l'écriture fonctionne (forceFill explicite).

## Requirements

- **FR-001**: `password_hash`, `biometric_face_reference_path`,
  `biometric_fingerprint_reference_path`, `email_bounced_at` retirés de
  `$fillable`.
- **FR-002**: `EmployeeService::create()` pose `password_hash` explicitement
  après création (pattern #4307).
- **FR-003**: `EmployeeImportController` pose `password_hash` explicitement.
- **FR-004**: test de régression : mass-assignment des 4 champs → non écrits ;
  écrivains légitimes (service/import/webhook) → toujours OK.
- **FR-005**: PHPStan strict vert ; CHANGELOG.md mis à jour.

## Success Criteria

- **SC-001**: `grep password_hash api/app/Core/Auth/Domain/Models/Employee.php`
  → absent de $fillable.
- **SC-002**: test mass-assignment des 4 champs → non persistés.
- **SC-003**: suite Feature HR/import/notification verte.

## Hors périmètre

- `biometric_face_enabled`/`biometric_fingerprint_enabled`/`biometric_consent_at`
  restent fillable (drapeaux de consentement, pas des secrets).
- La refonte des DTO (#4151) est déjà en place — uniquement le durcissement
  du modèle est traité ici.

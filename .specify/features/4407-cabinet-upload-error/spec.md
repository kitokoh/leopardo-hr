# Feature Specification: Upload Cabinet — gestion d'erreur ×3 apps (Closes #4407)

**Feature Branch**: `fix/4407-cabinet-upload-error`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4407 (P2, mobile — exception non gérée, fichier perdu)

## Contexte

`_pickAndUploadDocument` (employee/manager/hr) appelait `repo.uploadDocument`
sans try/catch : timeout/401/5xx → exception async non gérée, snackbar
« Envoi en cours... » bloqué, image sélectionnée perdue silencieusement.

## User Stories & Testing

### User Story 1 — Un échec d'upload est visible et récupérable (P1)

**Acceptance Scenarios**:
1. Given un upload qui échoue, When `_pickAndUploadDocument`, Then snackbar
   d'erreur affiché + état réinitialisé (pas d'exception non gérée).
2. Given un upload réussi, Then comportement inchangé (invalidate + succès).

## Requirements

### Functional Requirements

- **FR-001**: try/catch autour de `repo.uploadDocument` dans les 3 apps,
  snackbar d'erreur, `return` sans invalidate.
- **FR-002**: comportement nominal inchangé.

## Success Criteria

- **SC-001**: `flutter analyze` vert ×3 apps ; widget test upload KO (mock repo
  échouant) par app si le harnais existe.

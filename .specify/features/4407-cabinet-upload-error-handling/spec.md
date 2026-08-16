# Feature Specification: Mobile — upload Cabinet, gestion d'erreur (issue #4407)

**Feature Branch**: `fix/4407-cabinet-upload-error-handling`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA — `_pickAndUploadDocument` (cabinet_screen.dart ×3 apps :
employee/manager/hr) appelle `repo.uploadDocument(...)` sans try/catch. Tout
timeout/401/5xx → exception async non gérée : le snackbar « Envoi en cours... »
reste affiché pour toujours et l'image sélectionnée est perdue. Le repository
gère déjà retry/timeout (requestWithRetry + timeoutOverride) — le défaut est au
niveau écran.

## Problème

3 écrans identiques sans gestion d'erreur sur le chemin d'upload (les flows
download ont des try/catch, pas l'upload).

## Décision

- `try { await repo.uploadDocument(...); success snackbar + invalidate } catch { erreur snackbar }`
  dans les 3 apps, pattern identique.
- Réutilisation du pattern `if (!mounted) return;` existant (pas de nouveau
  lint use_build_context_synchronously).
- Hors périmètre : localisation des chaînes FR (dette #4194/#4303 suivie à part).

## User Scenarios & Testing

### User Story 1 — Un échec d'upload est visible et récupérable (Priority: P2)

**Independent Test**: `flutter analyze` + `flutter test` des 3 apps via
mobile-apps-ci (pas de toolchain Dart locale).

**Acceptance Scenarios**:

1. **Given** un upload qui échoue (timeout/401/5xx), **When** l'exception remonte,
   **Then** un snackbar d'erreur s'affiche (le snackbar « Envoi en cours... » est
   remplacé) et aucune exception async non gérée n'est levée.
2. **Given** un upload réussi, **When** le document est ajouté, **Then** la liste est
   invalidée et le snackbar succès s'affiche (comportement inchangé).
3. **Given** les 3 apps, **When** on compare les blocs, **Then** le correctif est
   identique (employee/manager/hr).

## Edge Cases

- `picker.pickImage` peut lancer (channel natif) : hors périmètre (comportement
  inchangé).
- `mounted` après await : gardé à chaque accès au contexte (pattern existant).

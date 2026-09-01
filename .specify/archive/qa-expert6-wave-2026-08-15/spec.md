# Feature Specification: QA Expert 6 — Vague de constats 2026-08-15 (issues #3427–#3437)

**Feature Branch**: `qa-expert6-wave-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Revue statique experte 2026-08-15 (4 scouts parallèles : API, vitrine/web, admin, mobile) + vérification anti-doublon GitHub (210 issues ouvertes à la création). Constats NOUVEAUX non couverts par les vagues précédentes (agent, expert, expert2, expert4, expert5).

## Problème

Malgré ~210 issues ouvertes et 5 vagues QA, 11 manquements inédits subsistent sur les 4 surfaces : 1 P1 sécurité API (bootstrapping Edge node par tout employé), 4 P2 (tenant integrity caméras, statut mobile disputed, crash contrat mobile, cohérence essai 14j), 6 P3 (TOCTOU markPaid, authz onboarding, DateTime.parse core, SEO case-studies, CSV injection PayrollView, libellés features bruts).

## User Stories & Testing

### User Story 1 — Surface API sécurisée (Priority: P1)

**Independent Test**: routes Edge restreintes à `api.manager` + test 403 employé non-manager ; CameraPermission 422 cross-tenant ; markPaid verrouillé (1 document) ; onboarding complete/skip restreint.

**Acceptance Scenarios**:
1. **Given** un employé non-manager, **When** POST /api/v1/edge, **Then** 403.
2. **Given** un employé d'une autre société, **When** CameraPermission store, **Then** 422.
3. **Given** 2 requêtes markPaid concurrentes, **When** exécutées, **Then** 1 seul document + 1 seule écriture ledger.

### User Story 2 — Surfaces mobile/web/admin cohérentes (Priority: P2)

**Independent Test**: Contract.fromJson sans crash sur start_date null ; statut disputed localisé ; FAQ/email 14j ; case-studies metadata ; CSV PayrollView échappé ; libellés features lisible.

**Acceptance Scenarios**:
1. **Given** un contrat sans start_date, **When** parsing mobile, **Then** pas de TypeError.
2. **Given** FAQ EN/TR, **When** lecture, **Then** « 14 jours » partout (aligné FR/AR + email).
3. **Given** un export PayrollView, **When** cellule commençant par `=`, **Then** préfixe neutralisé.

## Edge Cases

- Ne pas casser l'installation Edge légitime (token hash conservé, machine routes intactes).
- Ne pas recréer les routes mortes /ai-chat /vehicle-map /modules/rh (#2801).
- Garder la compatibilité des réponses API (pas de changement de contrat).

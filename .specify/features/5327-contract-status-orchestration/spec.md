# Feature Specification: Cohérence contrat ↔ statut employé (issue #5327, gap G4)

**Feature Branch**: `mod/hr/5327-contract-status-orchestration`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5327 [P2][HR] — « Cohérence contrat ↔ statut employé — orchestration du cycle de vie (G4) », source spec `.specify/features/hr-lifecycle/spec.md` (#5258).

## Problème

`activate`/`suspend`/`terminate` (`ContractLifecycleAction`, #3891) et l'archive employé sont des actions manuelles **distinctes** : un contrat actif peut coexister avec un employé suspendu (ou inversement), et rien n'empêche d'activer un contrat pour un employé archivé.

## Décision

Refactor HR (aucun changement Payroll, constitution §III) : **l'action de cycle de vie synchronise le statut de l'employé** :

| Transition contrat | Effet employé |
|---|---|
| `activate` (draft → active) | `employees.status = active` |
| `suspend` (active → suspended) | `employees.status = suspended` |
| `terminate` (dernier contrat actif/suspendu) | dispatch `EmployeeLastContractTerminated` (hook workflow de départ #5324) |
| toute transition sur employé **archivé** | refus `InvalidContractTransitionException` (422) — **invariant** |

**Invariant garanti** : jamais de contrat actif/suspendu sur un employé archivé. Le statut `departed` (workflow #5324, migration non mergée) sera posé par CE workflow à l'écoute de l'événement — `ContractLifecycleAction` n'en dépend pas (résilient).

**Périmètre** : module HR uniquement. Fichiers touchés : `ContractLifecycleAction` (refactor), nouvel event `EmployeeLastContractTerminated`, tests. Aucune route/contrat API modifié — les codes d'erreur existants (CONTRACT_*_INVALID_STATE, 422) couvrent les nouveaux refus.

## User Scenarios & Testing

### User Story 1 — Le statut employé suit le contrat (Priority: P2)

**Independent Test**: `php artisan test --filter=ContractStatusOrchestrationTest` (CI PostgreSQL 16) + `ContractLifecycleTest` (non-régression).

**Acceptance Scenarios**:

1. **Given** un employé suspendu, **When** on active son contrat draft, **Then** `employees.status = active`.
2. **Given** un contrat actif, **When** on le suspend, **Then** `employees.status = suspended`.
3. **Given** un employé archivé, **When** on active/suspend/termine un de ses contrats, **Then** 422 (invariant : jamais de contrat en cours sur un employé archivé).
4. **Given** le dernier contrat actif d'un employé, **When** on le termine, **Then** `EmployeeLastContractTerminated` est dispatché (hook #5324) et il ne reste aucun contrat actif.
5. **Given** deux contrats actifs, **When** on en termine un, **Then** aucun événement (l'employé a toujours un contrat en cours) et le statut reste `active`.

## Changement

- `api/app/Modules/HR/Application/Actions/ContractLifecycleAction.php` : orchestration (activate/suspend/terminate) + garde `assertEmployeeNotArchived` + `hasActiveContract`.
- `api/app/Events/EmployeeLastContractTerminated.php` (neuf) : hook du workflow de départ (#5324), porteur des identifiants uniquement.
- `api/tests/Feature/Contracts/ContractStatusOrchestrationTest.php` (neuf) : 7 scénarios (transitions, invariants, événement).
- CHANGELOG ; `ContractLifecycleTest` existant reste vert (non-régression).

## Hors périmètre

- Le workflow de départ complet (enregistrement, `departed`, SdC, attestation) = #5324 (branche parallèle, écoutera l'événement).
- L'archive employé (`EmployeeService::archive`) — inchangée (l'invariant inverse, contrats d'un employé archivé, reste une action RH manuelle documentée).
- Aucun changement Payroll, aucune route/API, aucun message i18n nouveau (codes 422 existants).

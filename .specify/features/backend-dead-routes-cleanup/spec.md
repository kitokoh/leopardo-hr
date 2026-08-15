# Feature Specification: Backend — fichier de routes mort (notification.php) + route dupliquée payment-documents

**Feature Branch**: `fix/2201-dead-routes`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2201 (QA hardening wave 2 — `.specify/features/qa-hardening-wave-2-2026-08-14/`)

## Problème

- `routes/modules/notification.php` : fichier no-op mort (tout commenté, gardé pour le require api.php:206).
- `routes/modules/payroll_engine.php:156-157` : `/payroll-runs/{payrollRun}/payment-documents` et `/payments/{payrollRun}/documents` → même action (doublon).

## User Stories & Testing

### User Story 1 — Plus de fichier mort (P2)
**Acceptance Scenarios**:
1. Given `routes/modules/notification.php` supprimé, When chargement des routes, Then aucune erreur (le require est retiré d'api.php).

### User Story 2 — Un seul chemin canonique payment-documents (P2)
**Acceptance Scenarios**:
1. Given le contrat mobile (`mobile-workflow-contracts.json` — seul `/me/payment-documents` est consommé), When suppression de l'alias `/payments/{payrollRun}/documents`, Then le chemin canonique `/payroll-runs/{payrollRun}/payment-documents` reste documenté (OpenAPI + miroir) et le test de contrat est mis à jour.
2. Given OpenAPI, When scan, Then aucun chemin fantôme `/payments/{payrollRun}/documents`.

## Plan technique
1. Supprimer `routes/modules/notification.php` + le require dans `api.php`.
2. Retirer l'alias `/payments/{payrollRun}/documents` de `payroll_engine.php`, d'`openapi.yaml` et du miroir `dev-hub/openapi/v1.yaml` ; retirer l'entrée du `FrontendApiContractTest`.
3. CHANGELOG + PR `fix/2201-...` `Closes #2201`.

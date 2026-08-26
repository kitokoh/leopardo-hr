# Feature Specification: Rapprochement bancaire — export CSV + UI de matching manuel (Closes #5523)

**Feature Branch**: `mod/accounting/5523-bank-export`
**Created**: 2026-08-26 | **Status**: In progress
**Issue**: #5523 (P2, backend, web)
**Spec**: `.specify/features/5523-bank-reconciliation-ui/spec.md`
**Anti-collision**: manquements de #5435 (Phase D, PR #5490) — export CSV + UI.

## Contexte

La Phase D a livré import + matching auto/manuel (endpoint) + état JSON. Manquent : l'export CSV de l'état et l'UI admin de la file de matching manuel.

## User Stories & Testing

### US-1 — Export CSV (P1, backend — livré)
1. Given relevé, When GET `/api/v1/accounting/bank-statements/{statement}/export`, Then CSV (lignes matched/pending/écart) — RBAC principal/comptable, isolation tenant.

### US-2 — UI de matching manuel (P1)
1. Given page `/accounting/reconciliation`, Then liste des relevés (GET /bank-statements).
2. Given relevé sélectionné, Then lignes (date, libellé, montant, statut, confidence) + proposition du matching auto (proposed_payment_id) + cartes de synthèse (soldes, écart, rapprochées/attente).
3. Given ligne pending + paiement choisi, Then POST /bank-statement-lines/{line}/match → ligne rapprochée (badge, matched_payment_id).
4. When bouton Exporter CSV, Then téléchargement.
5. Given erreur réseau, Then message + réessayer ; i18n ×4 (namespace `bankRecon.*`, 36 clés).

## Requirements

- FR-1 : endpoint export CSV (livré sur la branche) + tests Feature existants.
- FR-2 : `proposed_payment_id` exposé dans la sérialisation des lignes (métadonnées matching auto).
- FR-3 : page Next.js `/accounting/reconciliation` + tuile nav module Comptabilité.
- FR-4 : i18n ×4 `bankRecon.*` ; tests Jest (2) ; OpenAPI + SDK ; CHANGELOG.

## DoD

- [ ] Export CSV + UI matching manuel livrés
- [ ] Tests Feature + Jest
- [ ] i18n ×4 ; OpenAPI ; CHANGELOG

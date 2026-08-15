# Feature Specification: CI — palier préavis 90 j documenté (CI_COMPLIANCE.md §8)

**Feature Branch**: `fix/2264-ci-notice-period-docs`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2264

## Contexte
`CedeaoPayrollRules::noticePeriodDays()` (CI) implémente 3 paliers (30/60/90 j) mais
`CI_COMPLIANCE.md §8` ne documentait que 30/60 j — le palier 90 j pour ≥ 10 ans était
invisible et contredisait la matrice légale (employé/technicien max 2 mois).

## User Stories & Testing

### User Story 1 — Le comportement réel du moteur est documenté (P1)
**Acceptance Scenarios**:
1. Given la fiche CI, When lecture §8, Then les 3 paliers implémentés (30/60/90 j) sont documentés avec leur source.
2. Given la divergence (90 j vs matrice légale), When lecture, Then elle est explicitement marquée pilot + suivi expert.

## Requirements
- **FR-001**: §8 documente les 3 paliers implémentés et la divergence du palier 90 j (à valider expert OHADA-CI, suivi #1904).
- **FR-002**: Aucun changement de comportement du moteur (docs uniquement).

## Success Criteria
- **SC-001**: `docs/payroll/CI_COMPLIANCE.md` §8 à jour ; aucun changement de code.

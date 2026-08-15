# Feature Specification: Nettoyage mineur dashboard admin (boutons morts, mojibake, CRM, LogsView)

**Feature Branch**: `fix/2273-admin-minor-cleanup`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2273

## User Stories & Testing

### User Story 1 — Aucun bouton visible sans action (P1)
**Acceptance Scenarios**:
1. Given les vues auditées, When clic sur chaque bouton, Then une action réelle se produit (navigation, appel API, ou état explicite) — ou le bouton est supprimé.
2. Given `CompanyDetailView.vue:319` « Accès Super-Console », When clic, Then navigation réelle ou suppression.

### User Story 2 — Plus de mojibake (P1)
**Acceptance Scenarios**:
1. Given les templates FR, When scan des patterns `Ã‰`, `Ã `, `Ø§`, Then aucun résultat dans les vues admin.

### User Story 3 — CRM pipeline : le clic lead porte l'id (P2)
**Acceptance Scenarios**:
1. Given la liste des leads, When clic sur un lead, Then navigation/détail avec l'id du lead (pas un id vide/générique).

### User Story 4 — Pas de fichier orphelin (P2)
**Acceptance Scenarios**:
1. Given `system/LogsView.vue` non référencée, When décision, Then routée (si endpoint réel) ou supprimée.

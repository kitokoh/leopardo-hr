# Feature Specification: E2E portail client — parcours complet (Closes #5433)

**Feature Branch**: `mod/accounting/5433-portal-e2e`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5433 (P2, QA, tests, web)
**Spec**: `.specify/features/5433-portal-e2e/spec.md`
**Anti-collision**: chaînée sur #5429 → #5428 → #5430 → #5357.

## Contexte

Le portail client (backend #5357/#5225, web #5403/#5233) n'avait aucun test de bout en bout : les tests étaient unitaires/Feature (mock) et Jest (mock API). Le parcours réel « partage → email → lien → consultation → PDF » n'était jamais validé en CI.

## User Stories & Testing

### US-1 — Parcours complet backend (P1)

1. Given document + contact réels, When `SendDocumentEmail`, Then PDF généré + partage créé + email envoyé (lien).
2. When GET méta publique, Then 200 + numéro + statut `sent`.
3. When GET download, Then 200 + `application/pdf`.
4. When partage expiré, Then 404 (méta + download).
5. When token inconnu, Then 404.

### US-2 — E2E web (P1, API mockée)

1. Given page `/documents/shared/{token}` (API mockée), Then résumé visible (numéro, type, statut) + téléchargement (nom de fichier).
2. Given token invalide (404 mocké), Then écran « Lien invalide ou expiré », aucune donnée.
3. Given la route tokenisée, Then `Referrer-Policy: no-referrer` (header).

## Requirements

- FR-1 : Feature test `PortalJourneyE2ETest` (pattern #5285, code réel).
- FR-2 : Playwright `front/web/e2e/portal-document.spec.ts` — API mockée (`page.route`), aucun backend requis.
- FR-3 : assertion header `no-referrer` (#5429).

## DoD

- [x] Feature test parcours complet (6 étapes)
- [x] Spec Playwright (succès + invalide + header) branché dans `playwright.config.ts` (testDir e2e)
- [x] CHANGELOG + spec

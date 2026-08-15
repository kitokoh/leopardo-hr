# Feature Specification: /docs — ancres mortes alignées sur les sections réelles

**Feature Branch**: `fix/2274-web-docs-anchors`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2274

## Contexte
`front/web/src/app/(landing)/docs/page.tsx` : TOC (L32-95) et liens rapides (L456, L310) pointent vers ~34 ancres inexistantes ; seules 4 sections ont un id (`api-quickstart`, `webhooks-overview`, `sdk-overview`, `kiosk`). Aussi `#mobile-install` mort depuis `mobile/page.tsx:440`.

## User Stories & Testing

### User Story 1 — Chaque ancre du TOC résout une section (P1)
**Acceptance Scenarios**:
1. Given /docs, When clic sur chaque lien du TOC/liens rapides, Then scroll vers une section existante (id présent).
2. Given /mobile, When clic « installation mobile », Then /docs#mobile-install existe (id ajouté si contenu présent, sinon lien corrigé vers la section réelle).

### User Story 2 — Garde anti-ancre-morte (P2)
**Acceptance Scenarios**:
1. Given un scan des href `#*` internes, When vérification, Then chaque cible a un id correspondant sur la page.

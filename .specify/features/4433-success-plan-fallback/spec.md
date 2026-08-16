# Feature Specification: /checkout/success fallback plan neutre + code mort PricingSection (Closes #4433)

**Feature Branch**: `fix/4433-success-plan-fallback`
**Created**: 2026-08-16 | **Status**: In progress
**Base**: `origin/main`

## Problème
1. `/checkout/success` affiche « Pilot » pour tout `?plan` inconnu/vide (fallback trompeur).
2. Branche `free` inatteignable dans `getPlanCtaHref` (PricingSection).

## User Stories & Testing
### US1 — Page de succès honnête (P1)
1. Given `/checkout/success?plan=zzz`, When rendu, Then aucune mention « Pilot » (libellé plan neutre/vide).
2. Given `/checkout/success?plan=pilot`, When rendu, Then « Pilot » (inchangé).

### US2 — Pas de code mort (P2)
1. Given `getPlanCtaHref`, When plan Free, Then CTA `/signup?source=home_free` (unique).

## Requirements
- FR-001: fallback `|| ''` au lieu de `'Pilot'` dans success/page.tsx.
- FR-002: suppression de la 2e branche `free` (PricingSection).
- FR-003: CHANGELOG `### Fixed` ; tsc/eslint/jest verts.

## Critères de succès
- SC-001: `rg "source=pricing_free"` → 0.
- SC-002: success page sans « Pilot » fallback trompeur.
- SC-003: tests verts.

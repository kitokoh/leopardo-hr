# Feature Specification: CTA « Commencer gratuitement » → essai guidé (Closes #4183, volet CTA)

**Feature Branch**: `fix/4183-hero-cta-free-signup`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4183 (P1, web, conversion)

## Contexte

Les deux CTA principaux de `/pricing` (hero + bas de page) pointaient en dur
vers `/checkout?plan=free` — alias silencieux → `pilot` (29 €/mois payant,
PLAN_ALIASES) : un lead cliquant « Commencer gratuitement / sans carte bancaire »
arrivait sur un formulaire de paiement. Le plan Free (0 €/5 emp, PlanSeeder #2977)
est de retour sur la vitrine (#3883 → PR #4184) mais ses PR ne couvraient que
`getPlanHref` ; les deux liens héro/bas de page hardcodés restaient piégés.

## User Stories & Testing

### User Story 1 — Le CTA principal tient sa promesse (P1)

En tant que prospect attiré par le gratuit, je veux que « Commencer gratuitement »
mène à l'essai guidé sans carte.

**Acceptance Scenarios**:
1. Given `/pricing` (4 locales), When je clique le CTA héro, Then je suis dirigé
   vers `/signup?source=pricing_free` (essai guidé, aucun paiement).
2. Given `/pricing`, When je clique le CTA bas de page, Then idem.
3. Given aucun CTA de la vitrine, When grep `plan=free`, Then aucun href dur.

## Requirements

- **FR-001**: les liens héro (l. ~710) et bas de page (l. ~1243) de
  `pricing/page.tsx` pointent vers `/signup?source=pricing_free`.
- **FR-002**: `getPlanHref` (plan price '0') reste géré par #4184 (hors périmètre
  pour éviter le conflit de merge).

## Success Criteria

- **SC-001**: `grep 'href="/checkout?plan=free"'` → 0 sur la vitrine.
- **SC-002**: tsc, eslint, jest pricing verts.

## Hors périmètre

- Fallback `planNameToCheckoutKey() → free` : #4195/#4202 volet 2.
- Affichage `/checkout?plan=free` : #4195.
- Playwright e2e complet : non requis (2 hrefs statiques, couverts par grep + jest).

# Feature Specification: Suppression PricingSection/PricingCard mortes du dossier sections/ (Closes #4381)

**Feature Branch**: `fix/4381-dead-pricing-section`
**Created**: 2026-08-16 | **Status**: In progress
**Base**: `origin/main`

## Problème

`front/web/src/modules/vitrine/components/sections/PricingSection.tsx` et
`sections/PricingCard.tsx` sont des doublons morts : la home utilise la version
locale-aware `components/PricingSection.tsx` (import direct `(landing)/page.tsx:30`).
Le cluster `sections/` n'a aucun importeur en prod — uniquement le barrel
(`sections/index.ts`) et `sections/__tests__/imports.test.ts` + `PricingCard.test.tsx`.
La copie morte porte un badge « -20% » en dur et le calcul divergent
`Math.round(plan.price * 12 * 0.8)` (278/950 €/an au lieu de 288/948) — risque de
réutilisation accidentelle.

## User Stories & Testing

### User Story 1 — Une seule implémentation de tarifs (P1)

En tant que mainteneur, je veux une seule source de vérité pour la section tarifs.

**Acceptance Scenarios**:
1. Given `sections/PricingSection.tsx` supprimé, When `rg "sections/PricingSection"`,
   Then 0 résultat (hors .git).
2. Given `sections/PricingCard.tsx` supprimé, When `rg "sections/PricingCard"`,
   Then 0 résultat.
3. Given le barrel `sections/index.ts`, When import, Then plus d'export Pricing*.
4. Given `imports.test.ts`, When exécution, Then la suite passe sans références Pricing.

## Requirements

- **FR-001**: suppression de `sections/PricingSection.tsx`, `sections/PricingCard.tsx`,
  `sections/__tests__/PricingCard.test.tsx` + exports du barrel.
- **FR-002**: `imports.test.ts` réaligné (assertions Pricing retirées).
- **FR-003**: CHANGELOG sous `## [Unreleased]` → `### Removed`.
- **FR-004**: tsc, eslint, jest, mojibake, i18n validate verts.

## Critères de succès

- **SC-001**: build Next.js vert (page home rend la version locale-aware intacte).
- **SC-002**: 0 référence `sections/Pricing*` restante.
- **SC-003**: tests front verts (491+).

# Feature Specification: Badge « économies » annuel — vérité + dark mode case-studies (Closes #4202, volet 1 & 3)

**Feature Branch**: `fix/4202-savings-badge-truth`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4202 (P3, web — 3 volets ; volet 2 suivi via #4183/#4195)

## Contexte

1. La section tarifs de la home page hardcodait « Economisez 20% » / « Save 20% »
   (`PricingSection.tsx`) alors que le catalogue `vitrine-locale.ts` disait
   « jusqu'à 17 % » — deux sources contradictoires. Le rabais annuel réel :
   Pilot 29→24 ≈ 17 %, Operations 99→79 ≈ **20 %** → « jusqu'à 20 % » est le
   libellé honnête (source unique : catalogue).
2. `CaseStudyClient` gérait le dark mode via `useState(false)` local au lieu du
   hook partagé `useDarkMode()` → thème non persistant et non initialisé depuis
   localStorage/préférence système sur `/case-studies/[slug]`.

## User Stories & Testing

### User Story 1 — Un seul chiffre d'économies, honnête (P1)

En tant que visiteur, je veux voir le même libellé d'économies sur la home et
`/pricing`, correspondant au vrai rabais annuel.

**Acceptance Scenarios**:
1. Given la section tarifs de la home, When affichage, Then le badge d'économies
   vient du catalogue (`copy.pricing.annualSavings`), plus aucun littéral 20 % en dur.
2. Given le catalogue, Then « jusqu'à 20 % » (max réel : Operations 99→79).
3. Given `/case-studies/[slug]`, When dark mode activé, Then le thème persiste
   (localStorage/préférence système) via `useDarkMode()`.

## Requirements

- **FR-001**: suppression du map `savingsLabel` hardcodé dans `PricingSection.tsx` ;
  usage de `copy.pricing.annualSavings`.
- **FR-002**: `annualSavings` du catalogue aligné ×4 locales sur « jusqu'à 20 % ».
- **FR-003**: `CaseStudyClient` utilise `useDarkMode()` (pattern des autres pages).
- **FR-004**: tsc, eslint, jest, mojibake, validate i18n verts.

## Success Criteria

- **SC-001**: `grep "Economisez 20\|Save 20\|savingsLabel"` → 0 sur la vitrine.
- **SC-002**: les 4 locales portent « jusqu'à 20 % ».
- **SC-003**: tests front verts.

## Hors périmètre

Volet 2 de #4202 (fallback `planNameToCheckoutKey() → free`) : recouvre #4183/#4195,
traité séparément.

# ISSUE_4381 — PricingSection/PricingCard mortes dans sections/

> Spec Kit : `.specify/features/4381-dead-pricing-section/spec.md` · Issue : #4381
> Branche : `fix/4381-dead-pricing-section`

## Constat
`components/sections/PricingSection.tsx` + `PricingCard.tsx` : 0 importeur prod
(la home utilise `components/PricingSection.tsx` locale-aware). Seuls le barrel
et les tests les référencent. La copie morte hardcode « -20% » et recalcule
`Math.round(price*12*0.8)` (278/950 €/an) — divergence avec le canonique 24/79.

## Correctif
Supprimer le cluster (3 fichiers) + exports barrel + assertions imports.test.ts.
## Vérification
- `rg "sections/Pricing"` → 0.
- tsc/eslint/jest/build verts.

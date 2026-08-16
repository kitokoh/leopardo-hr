# ISSUE_4202 — Badge économies annuel + dark mode case-studies (volets 1 & 3)

> Spec Kit : `.specify/features/4202-savings-badge-truth/spec.md` · Issue : #4202
> Branche : `fix/4202-savings-badge-truth`

## Volets traités

1. **Badge « Économisez 20 % »** : map hardcodé supprimé de `PricingSection.tsx`,
   usage du catalogue (`copy.pricing.annualSavings`) ; catalogue aligné ×4 locales
   sur « jusqu'à 20 % » (max réel : Operations 99→79 ≈ 20 %).
3. **Dark mode /case-studies/[slug]** : `CaseStudyClient` utilise `useDarkMode()`
   (persistance localStorage + préférence système).

## Volet non traité (recouvrement)

2. Fallback `planNameToCheckoutKey() → free` : recouvre #4183/#4195 — suivi là-bas.

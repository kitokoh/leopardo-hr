# ISSUE_4433 — /checkout/success fallback « Pilot » trompeur + code mort PricingSection

> Spec Kit : `.specify/features/4433-success-plan-fallback/spec.md` · Issue : #4433
> Branche : `fix/4433-success-plan-fallback`

## Correctif
- success/page.tsx:121 : fallback `''` (plan inconnu → libellé neutre).
- PricingSection.tsx : suppression de la branche `free` inatteignable.

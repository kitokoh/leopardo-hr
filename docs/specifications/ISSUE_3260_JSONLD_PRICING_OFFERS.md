# Mini-spécification — Issue #3260

## Objectif

Aligner les données structurées `SoftwareApplication` avec les quatre plans affichés dans la page pricing et ne plus annoncer une fourchette ou un nombre d’offres obsolète.

## Correction

`OrganizationJsonLd` consomme `getPricingPlans('fr')`. Chaque plan devient une entrée Schema.org `Offer`. Free, Pilot et Operations exposent leur prix numérique ; Enterprise expose sa description et la mention « Tarif sur devis » sans prix inventé.

## Critères d’acceptation

1. Les quatre plans canoniques sont présents dans `offers`.
2. Les prix 0, 29 et 99 proviennent de `pricing.ts`.
3. Enterprise ne reçoit ni 199 €, ni 99 €, ni un faux prix numérique.
4. `AggregateOffer` obsolète, `highPrice=99` et `offerCount=3` disparaissent.
5. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3260  
Branche : `fix/3260-jsonld-pricing-offers`  
Date : 2026-08-15

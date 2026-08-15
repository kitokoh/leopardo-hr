# Mini-spec — Issue #3718

## Intention
Empêcher les CTA de la homepage pricing de rediriger silencieusement les plans Starter et Business vers le checkout gratuit.

## Contrat de routage
Les noms affichés sont normalisés vers les clés checkout canoniques : `Starter → pilot`, `Business → operations`, `Enterprise → enterprise`. Les alias historiques `Pilot`, `Operations` et `Scale` restent acceptés. Le plan `pilot` conserve son parcours d’essai sans carte via `/signup?source=home_pilot`.

## Implémentation
La fonction `planNameToCheckoutKey()` centralise le mapping dans `PricingSection.tsx`. Le CTA utilise cette fonction au lieu de branches incompatibles avec les noms actuels fournis par `pricing.ts`.

## Critères d’acceptation

| Plan affiché | Destination attendue |
|---|---|
| Starter | `/signup?source=home_pilot` |
| Business | `/checkout?plan=operations&billing=...` |
| Enterprise | `/checkout?plan=enterprise&billing=...` |

Les tokens de facturation mensuelle/annuelle et la branche Enterprise « sur devis » restent inchangés. Le test Jest couvre les noms actuels et les alias historiques.

## Validation

Le test Jest ciblé passe avec 7 assertions et `npx tsc --noEmit` passe sans erreur.

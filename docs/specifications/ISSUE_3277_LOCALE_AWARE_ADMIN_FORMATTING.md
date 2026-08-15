# Mini-spécification — Issue #3277

## Objectif

Supprimer les locales `fr-FR` codées en dur dans les écrans admin afin que dates et nombres suivent la préférence FR/EN/TR/AR.

## Correction

AnalyticsView utilise `toIntlLocale(localeStore.current)` pour les revenus et les dates. TaxSlabEditor utilise la même source pour les montants. ApplicantDetailModal et VehicleDetailModal utilisent désormais le locale store pour leurs dates.

Les devises métier restent celles du contrat affiché ; la correction porte sur la locale de présentation, pas sur une conversion monétaire.

## Critères d’acceptation

1. Les dates des quatre surfaces utilisent `toIntlLocale`.
2. Les nombres des Analytics et TaxSlabEditor utilisent la locale active.
3. Aucun `toLocaleDateString/toLocaleString('fr-FR')` ne reste dans les fichiers ciblés.
4. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3277  
Branche : `fix/3277-locale-aware-admin-formatting`  
Date : 2026-08-15

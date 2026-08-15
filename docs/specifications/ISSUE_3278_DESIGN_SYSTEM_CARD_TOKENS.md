# Mini-spécification — Issue #3278

## Objectif

Réduire l’incohérence visuelle du dashboard admin en remplaçant les combinaisons de classes legacy de cartes par les tokens `card` et `glass-card` du design system.

## Périmètre livré

Les vues Exports, Leaves, Predictions, Reports et Webhooks ainsi que les composants VehicleDetailModal, ApplicantDetailModal, UserDetailModal et NotificationPanel utilisent désormais les classes de carte partagées lorsque leurs combinaisons legacy étaient équivalentes.

Cette tranche ne fusionne pas les deux MetricCard : le composant analytique possède des props de tendance/progression propres, tandis que le composant commun est volontairement minimal. Une fusion ultérieure devra conserver les deux contrats ou introduire une API explicitement versionnée.

## Critères d’acceptation

1. Les cartes ciblées utilisent `card` ou `glass-card` au lieu des combinaisons legacy ciblées.
2. Le comportement et les props Vue restent inchangés.
3. Le lint et le build du dashboard passent.
4. `git diff --check` passe.

## Trace Spec Kit

Issue : #3278  
Branche : `fix/3278-design-system-card-tokens`  
Date : 2026-08-15

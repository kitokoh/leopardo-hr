# Mini-spécification — Issue #3274

## Objectif

Empêcher la console admin d’afficher une santé « bonne » ou des KPI nuls lorsqu’un chargement API échoue silencieusement.

## Correction

Le store dashboard initialise `systemHealth` à `unknown`, expose `loadError`, et conserve cet état après un échec des métriques initiales ou d’un rafraîchissement. `DashboardLayout` affiche alors un bandeau `role=alert` indiquant que les indicateurs peuvent être incomplets.

`PredictionsView` conserve séparément les erreurs de notifications, turnover et absentéisme. Chaque erreur est rendue dans son panneau au lieu de transformer l’échec en liste vide ou KPI nul.

## Critères d’acceptation

1. `systemHealth` ne démarre plus à `good` avant une réponse API.
2. Un échec du dashboard est visible dans le shell global.
3. Les trois chargements prédictifs exposent chacun une erreur visible.
4. Les données valides continuent d’effacer l’erreur correspondante.
5. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3274  
Branche : `fix/3274-visible-admin-load-errors`  
Date : 2026-08-15

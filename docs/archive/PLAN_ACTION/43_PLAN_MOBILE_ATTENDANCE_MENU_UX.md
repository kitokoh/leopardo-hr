# Plan 43 - Menu pointage employee mobile

## Objectif

Finaliser le comportement du menu haut de la page pointage employee afin qu'il serve des actions utiles et deja fonctionnelles.

## Probleme corrige

Le menu haut affichait `Taches du jour`, mais ouvrait encore un message de placeholder. Le libelle `Historique` envoyait aussi vers `Mon mois complet`, ce qui melangeait deux intentions differentes.

## Livrables

- `Taches du jour` ouvre une bottom sheet connectee au provider `todayTasksProvider`.
- Les taches peuvent etre cloturees depuis cette bottom sheet avec le flux existant.
- `Historique` ouvre la route `/history`.
- `Preferences` et `Parametres` ouvrent les reglages.
- Aucun retour de l'option `Modifier` dans le menu haut.

## Validation attendue

- Menu haut `...` : Taches du jour, Historique, Preferences, Parametres.
- Menu bas/lignes jour : conserve l'acces a la demande de modification.
- Flutter analyze employee via GitHub Actions.

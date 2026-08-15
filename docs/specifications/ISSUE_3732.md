# Mini-spec — Issue #3732

## Contrat d’accessibilité

| Surface | Exigence |
|---|---|
| Recherche FAQ | label programmatique `faq-search` et icône décorative masquée |
| Accordéon FAQ | chaque déclencheur expose `aria-expanded` et `aria-controls` |
| Réponse FAQ | panneau ouvert expose `role=region` et `aria-labelledby` |
| Drawer mobile | label issu de `copy.nav.menuLabel`, jamais une chaîne FR en dur |
| Sous-menus mobile | chaque bouton expose `aria-expanded` |

## Locales et RTL

Les attributs sont indépendants de la langue affichée ; le texte de label vient du catalogue de copie existant et le comportement RTL arabe est conservé.

## Validation

Le diff est validé par `git diff --check`. Les tests frontend complets sont exécutés par la CI avec les dépendances verrouillées du projet.

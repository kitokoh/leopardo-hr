# Mini-spec — Issue #3734

## Problème

Le Footer construisait ses hrefs par position et renvoyait `#` pour toute entrée inconnue. Une évolution d’une locale pouvait donc produire un lien silencieusement mort.

## Contrat

| Cas | Résultat |
|---|---|
| Entrée connue | href canonique conservé |
| Entrée inconnue | aucun lien rendu ; jamais de fallback `#` |
| Blog désactivé | entrée Blog non rendue, car la route est volontairement indisponible |
| Locales FR/EN/TR/AR | les libellés restent localisés et les routes restent first-party |

## Implémentation

`getFooterHref` retourne maintenant `string | null`. Le composant ne rend pas une entrée sans route valide, ce qui transforme une dérive de données en défaut visible/testable au lieu d’une navigation morte. Un test couvre les routes connues et l’absence de fallback.

## Évolution suivante

La structure de données footer pourra migrer vers `{ label, href }` par locale ; cette PR supprime immédiatement le risque utilisateur du fallback silencieux et garde les routes canoniques existantes.

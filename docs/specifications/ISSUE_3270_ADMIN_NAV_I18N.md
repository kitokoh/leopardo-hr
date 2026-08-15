# Mini-spécification — Issue #3270

## Objectif

Faire en sorte que la navigation persistante du dashboard admin respecte les quatre locales existantes au lieu d’afficher des titres français codés en dur.

## Périmètre livré

`Sidebar.vue` utilise `translate` pour son aria-label et pour les 25 entrées de navigation. Un catalogue `navigation` de 26 clés est ajouté aux fichiers FR, EN, TR et AR. Les fallbacks conservent les libellés actuels si une clé est absente.

Cette tranche cible le chrome commun visible sur toutes les vues ; elle prépare les vues métier restantes à la même migration sans changer leur routage ni leurs permissions.

## Critères d’acceptation

1. Le Sidebar ne contient plus de titres de navigation codés en dur hors fallbacks explicites.
2. Les quatre catalogues contiennent les 26 clés `navigation`.
3. L’aria-label du menu principal est traduit.
4. Le build et le lint du dashboard passent.
5. Les fallbacks FR restent disponibles pour les clés non encore migrées.

## Trace Spec Kit

Issue : #3270  
Branche : `fix/3270-admin-navigation-i18n`  
Date : 2026-08-15

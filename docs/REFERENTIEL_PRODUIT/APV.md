# APV - Architecture Produit Vivante

> Source de verite architecturale de Leopardo RH.
> Ce document decrit la vision active et les lois d'evolution du produit.
> Il ne decrit pas a lui seul l'inventaire exact de `main`.

Pour l'etat reel du depot, utiliser :

1. `../../PILOTAGE.md`
2. `../GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
3. les routes Laravel et les tests

## Pitch

Leopardo RH doit rester un produit mobile-first, modulaire, lisible et evolutif, sans casser les parcours deja livres.

## Les 4 piliers

1. Conversationnelle : l'experience doit pouvoir guider l'utilisateur plutot que l'abandonner face a une interface froide.
2. Mobile-first : les parcours employe partent du mobile; le web sert prioritairement de back-office manager.
3. Modulaire : les briques fonctionnelles doivent pouvoir etre activees, etendues et gouvernees proprement.
4. Vivante : le produit doit evoluer sans casser ses contrats critiques.

## Ce que ce document veut dire

- il fixe la direction produit
- il fixe les lois de coherence
- il ne dit pas automatiquement ce qui est ou n'est pas deja merge sur `main`

## Lois de coherence

1. Un nouveau developpement ne doit pas contredire les parcours deja livres sans decision explicite.
2. Les contrats critiques mobile/web doivent rester stables ou etre versionnes.
3. La documentation produit, le code et le pilotage doivent etre reconcilies apres chaque extension majeure.
4. Les modules ne doivent pas devenir un pretexte pour recreer plusieurs verites concurrentes.

## Decision importante d'avril 2026

Le depot a deja depasse le MVP historique.

Consequence :

- certaines briques autrefois considerees "Phase 2" ou "plus tard" existent deja sur `main`
- la question n'est plus "est-ce que le repo est reste strictement MVP ?"
- la question devient "comment garder une vision produit claire malgre l'extension deja visible du code ?"

## Ce qui reste voulu

- un coeur RH simple a comprendre
- une reprise rapide pour tout nouveau developpeur
- une evolution modulaire sans rupture documentaire
- une priorite donnee a la stabilisation beta avant toute nouvelle derive

## Gouvernance

Toute decision qui change la vision active doit aussi verifier :

1. `../../PILOTAGE.md`
2. `ROADMAP.md`
3. `../GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`
4. `../../CHANGELOG.md`

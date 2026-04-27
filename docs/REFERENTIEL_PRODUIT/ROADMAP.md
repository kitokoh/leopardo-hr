# ROADMAP - Leopardo RH

> Sequencement des priorites produit et techniques.
> Ce document donne la direction. Le backlog courant de reprise reste
> `../GESTION_PROJET/PROCHAINES_ACTIONS_MAIN_2026-04-27.md`.

## Lecture correcte

- `PILOTAGE.md` dit ou nous en sommes
- ce document dit ou nous voulons aller
- `PROCHAINES_ACTIONS_MAIN_2026-04-27.md` dit quoi faire ensuite

## Phase actuelle

### Phase active - Stabilisation beta et verite documentaire

Le produit a deja depasse le MVP historique dans le code.
La priorite immediate n'est donc pas d'ouvrir un nouveau grand front,
mais de :

1. rendre les documents maitres coherents avec `main`
2. remettre la documentation API a niveau
3. consolider les parcours pilotes
4. corriger les retours prioritaires avant toute nouvelle derive

## Etat deja visible sur `main`

- coeur MVP RH livre
- i18n en place
- hardenings P0/P1/P2
- onboarding public
- modules RH etendus visibles dans les routes
- module cameras visible dans les routes

## Horizon recommande

### Horizon 1 - Clarifier et stabiliser

- documents maitres alignes
- OpenAPI plus proche du code reel
- reprises de session simplifiees
- retours pilotes mieux priorises

### Horizon 2 - Fiabiliser les contrats

- parcours auth / employees / attendance / onboarding documentes proprement
- tests de non-regression renforces la ou les surfaces bougent
- zone grise reduite entre "etat courant" et "cible"

### Horizon 3 - Nouvelles extensions modulees

Seulement apres les deux horizons precedents :

- nouvelles extensions RH
- enrichissements cameras
- autres modules activables
- IA conversationnelle plus ambitieuse

## Regles de priorisation

1. une correction de verite documentaire prioritaire vaut mieux qu'une nouvelle spec de plus
2. une regression pilote prioritaire vaut mieux qu'une nouvelle feature speculative
3. une documentation API fausse doit etre corrigee avant de servir de contrat d'integration
4. on n'ouvre pas un nouveau front si la reprise du projet devient plus confuse

## Rappel important

Les anciennes formulations du type "ce module n'entre jamais dans `main`"
ne sont plus fiables comme description du depot actuel.

La roadmap doit donc rester :

- ambitieuse comme vision
- sobre comme promesse
- exacte sur le fait que `main` est deja plus large que le MVP d'origine

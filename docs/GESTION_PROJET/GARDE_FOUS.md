# GARDE-FOUS - Regles de survie du projet
# Version 5.1 | 27 Avril 2026

Ce fichier reste actif pour eviter la derive, mais il ne decrit pas a lui seul l'etat reel de `main`.

## Lire dans cet ordre

1. `../../PILOTAGE.md`
2. `PROCHAINES_ACTIONS_MAIN_2026-04-27.md`
3. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`

## Garde-fou 1 - Anti scope creep

Avant d'ajouter quoi que ce soit, poser la question :

> Est-ce que cette action aide directement a livrer, stabiliser ou documenter proprement la valeur deja visible sur `main` ?

- Oui : on peut continuer.
- Non : cela doit etre arbitre dans `PILOTAGE.md` avant implementation.

### Important

L'ancienne blacklist MVP n'est plus suffisante pour lire le projet :

- `main` contient deja plus que le MVP historique
- certains modules autrefois consideres "hors scope" existent deja dans le depot
- on ne deduit donc jamais l'etat reel du produit a partir d'une ancienne checklist MVP

## Garde-fou 2 - Verite documentaire

En cas de contradiction :

1. les routes Laravel et les tests priment pour l'etat reel
2. `PILOTAGE.md` prime pour le pilotage courant
3. `docs/REFERENTIEL_PRODUIT/` prime pour la vision active
4. `docs/dossierdeConception/` decrit majoritairement la cible
5. `docs/notes/` est non canonique

## Garde-fou 3 - Documentation utile seulement

La documentation doit :

- clarifier une decision
- faciliter la reprise
- ou eviter une erreur de lecture

Elle ne doit pas :

- dupliquer une autre source canonique
- recreer une archive sous un nouveau nom
- ou devenir un backlog parallele cache

## Garde-fou 4 - Porte verte

On ne passe jamais a l'etape suivante si les verifications precedentes sont rouges.

Verification minimale avant de continuer sur une zone code :

1. tests ou checks pertinents connus
2. documentation canonique de la zone lue
3. absence de contradiction non traitee avec `PILOTAGE.md`

## Garde-fou 5 - Isolation tenant et securite

Toute modification backend doit conserver :

- l'isolation tenant
- les garde-fous d'authentification
- la coherence des contrats JSON critiques pour mobile/web

Si une modification fragilise l'isolation ou la securite :

1. stop
2. documenter le risque
3. corriger avant toute extension

## Garde-fou 6 - Une seule source pour "quoi faire ensuite"

Le document canonique de reprise operatoire est :

- `PROCHAINES_ACTIONS_MAIN_2026-04-27.md`

On ne doit pas recreer ailleurs une seconde liste de priorites concurrente sans mettre a jour ce fichier.

## Garde-fou 7 - Fin de session obligatoire

Avant de terminer une PR ou une session :

1. mettre a jour `CHANGELOG.md` si une zone critique a change
2. mettre a jour `PILOTAGE.md` si l'etat reel ou la prochaine action changent
3. mettre a jour `PROCHAINES_ACTIONS_MAIN_2026-04-27.md` si l'ordre des priorites change
4. laisser les documents d'archive en archive

## Garde-fou 8 - Simplicite de reprise

Un nouveau developpeur doit pouvoir comprendre en moins de 5 minutes :

- ce qui est deja livre
- ce qui reste a faire
- quel document fait foi
- ou commencer

Si un document empeche cela, il doit etre corrige ou declassifie.

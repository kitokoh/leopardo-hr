# Prochaines actions - `main`

Date de reference : 2026-04-27

## Objet

Ce document est le point d'entree court pour tout developpeur, agent IA ou chef de projet
qui arrive sur le depot et veut savoir immediatement :

- ce qui est deja livre sur `main`
- ce qui reste a faire
- dans quel ordre le faire
- quels documents doivent etre mis a jour a la fin

## Lire avant toute action

1. `../../PILOTAGE.md`
2. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
3. `../../api/routes/api.php`
4. `../../api/routes/modules/rh.php`
5. `../../api/routes/modules/cameras.php`

## Etat reel resumee

- `main` contient deja le MVP livre, l'i18n, les hardenings P0/P1/P2, les modules RH etendus, le module cameras et l'onboarding public.
- La documentation n'est pas encore totalement alignee sur cette realite.
- Le principal risque actuel n'est plus l'absence de code coeur, mais la divergence entre vision, pilotage, CDC/reponse CDC et surface API reelle.

## Priorites operatives en cours

### Priorite 1 - refermer les ecarts documentaires maitres

Objectif : faire en sorte que `PILOTAGE.md`, `docs/REFERENTIEL_PRODUIT/`, `docs/GESTION_PROJET/` et le dossier de reponse au CDC racontent la meme histoire.

Actions :

1. verifier que `PILOTAGE.md`, `docs/REFERENTIEL_PRODUIT/APV.md`, `docs/REFERENTIEL_PRODUIT/ROADMAP.md` et `docs/GESTION_PROJET/DOSSIER_REPONSE_AU_CAHIER_DES_CHARGES.md` restent alignes apres chaque PR
2. maintenir `docs/README.md`, `docs/GESTION_PROJET/README.md` et `docs/dossierdeConception/README.md` comme points d'entree pour la reprise
3. corriger en priorite toute nouvelle contradiction entre ces documents et les routes/tests
4. eviter de recreer un second backlog cache dans `docs/notes/` ou dans un fichier non canonique

Definition du fini :

- un lecteur non technique comprend en moins de 5 minutes ce qui est reel, ce qui est cible, et ce qu'il reste a faire
- aucun document maitre ne contredit frontalement les routes Laravel

### Priorite 2 - remettre la documentation API a niveau

Objectif : ne plus utiliser les specs cibles comme preuve d'implementation.

Actions :

1. auditer `api/openapi.yaml` contre les routes reelles
2. ajouter les endpoints publics d'onboarding
3. separer explicitement les endpoints "etat courant" des endpoints "cible"
4. verifier les payloads et codes HTTP des endpoints les plus critiques :
   - auth
   - employees
   - attendance
   - onboarding
   - invitations
   - cameras

Definition du fini :

- `api/openapi.yaml` n'annonce plus de routes absentes de `main` dans les zones revisees
- les clients mobile/web peuvent s'appuyer sur la spec sans relire chaque controller pour les parcours couverts

### Priorite 3 - pilotage beta

Objectif : rendre visible ce qui reste a faire hors code pour passer de "base livree" a "usage pilote propre".

Actions humaines/projet :

1. inviter 3 a 5 prospects beta
2. collecter les retours prioritaires
3. decider les corrections UX/API les plus bloquantes
4. preparer l'ouverture des inscriptions

Actions dev attendues apres retour pilote :

1. corriger les regressions fonctionnelles remontees par les pilotes
2. ajouter les tests de non-regression associes
3. mettre a jour le changelog et le pilotage

## Ordre recommande pour le prochain developpeur

1. verifier `git status` et la branche de travail
2. lire `../../PILOTAGE.md`
3. lire ce document
4. choisir une seule priorite parmi les trois ci-dessus
5. verifier les routes/controllers/tests qui font foi
6. implementer
7. mettre a jour la documentation canonique touchee par la PR

## Regles de sortie de session

Avant de terminer une PR ou une session de travail :

1. mettre a jour `CHANGELOG.md` si `api/`, `mobile/`, `PILOTAGE.md`, `.github/` ou `docs/GESTION_PROJET/` changent
2. mettre a jour `PILOTAGE.md` si l'etat reel, la prochaine action ou le backlog courant changent
3. mettre a jour ce document si l'ordre des priorites change
4. ne jamais utiliser `docs/notes/CONTINUE_v2.md` comme source de reprise canonique

## Ce document prime sur

- `docs/notes/CONTINUE_v2.md`
- les anciens prompts v2
- les checklists historiques non remises a jour

## Revue rapide "que faire maintenant ?"

Si rien d'urgent n'est signale par un humain, faire dans cet ordre :

1. finir l'alignement des documents maitres
2. remettre `api/openapi.yaml` au niveau du code reel
3. traiter les retours des pilotes beta

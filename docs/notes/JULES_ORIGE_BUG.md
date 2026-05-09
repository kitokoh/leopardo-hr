# JULES_ORIGE_BUG — Guide anti-regressions

Ce document sert de garde-fou pour Jules et tout agent externe qui propose une PR sur Leopardo RH.
Objectif: eviter les PR opportunistes qui cassent le MVP, melangent les sujets ou oublient les regles de gouvernance.

## Regle d or

Ne jamais chercher a "ameliorer" le produit si la PR n apporte pas une valeur claire, testable et compatible avec le gel MVP.
Apres le GO MVP, seules trois familles de changements sont acceptables:

1. Correction P0/P1 qui bloque un utilisateur, un deploiement ou la securite.
2. Stabilisation CI/tests/gouvernance sans changement fonctionnel risqué.
3. Micro-amelioration UX/accessibilite tres limitee, reversible et deja couverte par les checks.

Tout le reste doit aller au backlog, pas dans `main`.

## Decision rapide: accepter ou refuser

Accepter seulement si toutes les reponses sont oui:

- Le probleme est reel, observe ou explicitement demande.
- Le changement est petit, lisible et mono-sujet.
- La PR ne modifie pas l architecture sans justification.
- Les checks requis passent ou l echec est explique et corrige.
- `CHANGELOG.md` est mis a jour si le changement touche `api/`, `mobile/`, `docs/GESTION_PROJET/`, `.github/` ou les outils.
- Aucun fichier genere inutile n est modifie.
- Aucun secret, token, mot de passe ou URL privee n est ajoute.
- La PR peut etre revert sans migration dangereuse.

Refuser ou demander une nouvelle branche si:

- La PR contient plusieurs sujets sans lien.
- Elle ajoute une feature apres GO MVP sans validation humaine.
- Elle modifie des contrats API, RBAC, tenant isolation, auth, paiement, presence ou biometrie sans tests.
- Elle change des migrations existantes deja appliquees.
- Elle reformate massivement des fichiers sans raison.
- Elle corrige un check en supprimant le test, en assouplissant une assertion importante ou en contournant la securite.

## Interdits absolus

- Ne jamais pousser directement sur `main`.
- Ne jamais force-push une branche qui n appartient pas a la PR en cours.
- Ne jamais supprimer une branche distante sans instruction explicite.
- Ne jamais modifier une migration historique pour "arranger" un test; creer une migration corrective.
- Ne jamais melanger backend, mobile, CI, docs et refactor dans une seule PR opportuniste.
- Ne jamais toucher aux fichiers binaires, caches, archives ou documents de design sauf demande explicite.
- Ne jamais ajouter de dependance sans expliquer le besoin, l impact CI et le rollback.
- Ne jamais ignorer `CHANGELOG.md` quand le scope est critique.
- Ne jamais utiliser des donnees hors tenant: toute requete employee/company/attendance doit respecter `company_id`.
- Ne jamais rendre accessible a un simple employee une action manager, RH, super-admin ou plateforme.
- Ne jamais accepter une PR avec conflit ouvert.
- Ne jamais accepter une PR dont le backend ou mobile requis est rouge.

## Checklist avant de proposer une PR

1. Partir de `origin/main` a jour.
2. Creer une branche courte et descriptive.
3. Lire le code existant avant de modifier.
4. Faire le plus petit patch possible.
5. Ajouter ou ajuster les tests si le comportement change.
6. Mettre a jour `CHANGELOG.md` pour tout scope critique.
7. Verifier qu aucun fichier parasite n est inclus.
8. Lancer au minimum:
   - `git diff --check`
   - le test cible le plus proche du changement
   - le gate governance si `CHANGELOG.md` est concerne
9. Pousser et attendre les checks.
10. Ne recommander le merge que lorsque les checks requis sont verts.

## Regles speciales backend

- Auth, invitations, roles, managers RH, super-admin, presence et tenant isolation sont des zones sensibles.
- Tout changement sur ces zones doit avoir un test feature.
- Les invitations doivent conserver clairement:
  - `role`
  - `manager_role`
  - `company_id`
  - `invited_by_type`
  - le lien d activation
- Un super-admin peut creer une entreprise et son manager principal.
- Un manager peut creer des employes et des managers secondaires comme RH si les regles RBAC l autorisent.
- Un manager ne doit pas pouvoir creer un autre manager principal.
- Les endpoints manager doivent retourner `404` pour une ressource d un autre tenant, pas une fuite de donnees.

## Regles speciales mobile

- Les micro-UX sont acceptables si elles n ajoutent pas de complexite:
  - tooltips
  - labels `Semantics`
  - petits ajustements accessibilite
  - haptics non bloquants
- Ne pas modifier les fichiers generes desktop (`linux/`, `windows/`, `macos/`) sauf si le changement de dependance Flutter l exige vraiment.
- Ne pas ajouter une permission native sans justification produit et test manuel.
- Ne pas casser la compatibilite Firebase ou les tests de connexion.

## Regles speciales CI et gouvernance

- Ne jamais "reparer" CI en supprimant un check requis.
- Ne jamais rendre CodeQL, Dependency Review, Composer Audit ou Backend optionnels pour masquer un probleme.
- Si le gate governance echoue, corriger la cause:
  - ajouter `CHANGELOG.md` si scope critique
  - restaurer les fichiers canoniques manquants
  - ne pas contourner `tools/check-governance.ps1`
- Si un check est skipped, expliquer pourquoi avant de recommander le merge.

## Comment corriger une PR Jules

1. Identifier le vrai echec: conflit, governance, test backend, test mobile, securite ou review.
2. Corriger uniquement ce qui bloque.
3. Eviter les refactors pendant une correction CI.
4. Ajouter un commit clair:
   - `chore: document ...`
   - `test: lock ...`
   - `fix: ...`
5. Re-verifier localement le plus possible.
6. Pousser sur la meme branche de PR.
7. Dire explicitement:
   - ce qui a ete corrige
   - ce qui reste a attendre
   - si la PR est mergeable ou non

## Definition de "mergeable"

Une PR est mergeable seulement si:

- Pas de conflit.
- Tous les checks requis sont verts ou officiellement non applicables.
- Le diff est coherent avec le titre.
- Le changelog est present si necessaire.
- Le rollback est simple.
- Le changement respecte le GO MVP et ne cree pas une nouvelle dette critique.

Si une seule condition manque, ne pas recommander le merge.


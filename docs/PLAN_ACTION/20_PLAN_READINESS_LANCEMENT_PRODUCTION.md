# 20 - Plan readiness lancement production

Date : 2026-05-22

## Objectif

Transformer les derniers controles avant acquisition marketing en garde-fous produit mesurables : un tenant doit pouvoir savoir s'il est pret a accueillir des utilisateurs reels, a recevoir des prospects et a exploiter les modules critiques sans intervention manuelle fragile.

## Pourquoi ce plan maintenant

Les plans 18 et 19 ont ferme deux sujets majeurs : experience de connexion/client et communication interne. Le prochain risque n'est plus seulement technique ; c'est le decalage entre trafic marketing, onboarding client, donnees RH minimales et support operationnel. Plan 20 installe donc un cockpit de readiness go-live.

## Lot 20.1 - API readiness tenant

Priorite : critique

Statut : livre 2026-05-22.

Livrables :

- Endpoint `GET /api/v1/launch-readiness` reserve aux managers `principal` et `rh`.
- Score 0-100 et indicateur `go_live_ready`.
- Blocages requis : profil entreprise, acces manager/RH, base collaborateurs, preferences/audit communication.
- Verifications non bloquantes : paie minimale, entree pointage, instrumentation UX client.
- Isolation tenant stricte sur tous les compteurs.

Tests :

- Manager principal voit un tenant pret.
- Manager RH voit les blocages requis.
- Employe standard recoit `403`.

## Lot 20.2 - Exploitation support

Priorite : haute

Statut : livre 2026-05-22 pour le portail client.

Livrables :

- Carte readiness dans le dashboard client manager, consommee depuis `GET /api/v1/launch-readiness`.
- Les managers `principal`/`rh` voient le score, les blocages requis et les prochaines actions sans bloquer le chargement principal du dashboard.
- Le super-admin peut s'appuyer sur le meme contrat API pour une future vue multi-tenant, sans dupliquer les regles.

## Extensions post-plan - Go-live automatique

Priorite : moyenne

Statut : hors definition de done Plan 20, a prioriser apres retours terrain.

Livrables :

- Webhook interne ou notification support quand un tenant devient `go_live_ready=false` apres avoir ete pret.
- Historique des scores readiness par tenant.
- Export CSV mensuel pour pilotage Customer Success.

## Definition of done

- Le manager peut savoir clairement pourquoi son espace n'est pas pret.
- Le support peut prioriser les clients a risque.
- Les checks sont bases sur des donnees reelles, pas sur des declarations.
- Les frontends peuvent consommer un contrat API stable.
- Les controles sont documentes dans OpenAPI, RBAC et scenarios CI.

# RECETTE UAT — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-903 (#6232) — recette UAT du pilote RestaurantManager (gate `recette`, MAT-018).
> **Mode d'emploi :** exécuter chaque scénario sur l'environnement de recette (tenant pilote activé), reporter le résultat (✅/❌ + preuve), puis faire signer le cahier par le chef de projet.

## Cahier de recette

| # | Scénario | Étapes clés | Attendu | Résultat | Preuve |
|---|---|---|---|---|---|
| R1 | Service en salle complet (GJ-RESTO-01) | caisse → table → commande dine_in → article → submit → cuisine start/ready → serve → bill → pay cash → close table → close caisse | Flux complet sans 409/422 ; écart de caisse = 0 | ⬜ | lien CI `RestaurantGoldenJourneyTest` |
| R2 | Commande à emporter | commande takeaway + paiement | Statuts corrects jusqu'à `paid` | ⬜ | |
| R3 | Livraison complète | zone → rider → assign → out_for_delivery → deliver | Commande `served` après livraison | ⬜ | |
| R4 | Livraison annulée | assign → cancel | Commande retourne `ready` ; livreur libéré | ⬜ | |
| R5 | Réservation + conflit | créneau libre → réserver → même table/date → 409 | Conflit refusé ; check-in OK | ⬜ | |
| R6 | No-show + rappel | réservation confirmée dépassée → job ; réservation J-1 → job | `no_show` unique ; rappel unique | ⬜ | |
| R7 | Stock & COGS | décrément à la vente ; rapport COGS = recettes × coût moyen | Chiffres cohérents | ⬜ | |
| R8 | Fidélité | commande payée → points (une seule fois) ; échange > solde → 422 | Crédit exact ; jamais négatif | ⬜ | |
| R9 | Promotions | code valide → remise bornée ; expiré/épuisé → 422 | Bornes respectées | ⬜ | |
| R10 | Rapports & export | sales/kpis cohérents ; export CSV rejouable + URL signée valide 15 min | Mêmes chiffres que les données ; rejeu = même fichier | ⬜ | |
| R11 | Sécurité & isolation | token autre tenant → 404 ; appel sans flag → 403 | Aucune fuite cross-tenant | ⬜ | |

## Règles de recette

1. Chaque scénario doit être exécuté sur l'environnement de recette (jamais sur prod).
2. Une ❌ bloque la recette : ouvrir une issue de correctif (label `BC-25 RESTAURANT`) et repasser le scénario après correctif.
3. La recette n'est **signée** (GO partiel) que lorsque tous les scénarios sont ✅.

## Signature

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | | | |
| Responsable technique | | | |
| Représentant métier (restaurateur pilote) | | | |

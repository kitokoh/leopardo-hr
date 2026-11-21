# RECETTE UAT — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-903 (#6232) — recette UAT du pilote RestaurantManager (gate `recette`, MAT-018).
> **Mode d'emploi :** exécuter chaque scénario sur l'environnement de recette (tenant pilote activé), reporter le résultat (✅/❌ + preuve), puis faire signer le cahier par le chef de projet.

## Cahier de recette

| # | Scénario | Étapes clés | Attendu | Résultat | Preuve |
|---|---|---|---|---|---|
| R1 | Service en salle complet (GJ-RESTO-01) | caisse → table → commande dine_in → article → submit → cuisine start/ready → serve → bill → pay cash → close table → close caisse | Flux complet sans 409/422 ; écart de caisse = 0 | ✅ | **Automatisé** — `CI=true php artisan test tests/Feature/Restaurant/RestaurantGoldenJourneyTest.php` → 1 passed (23 assertions). Suite complète : `tests/Feature/Restaurant` → 397 passed (1 520 assertions), 0 échec |
| R2 | Commande à emporter | commande takeaway + paiement | Statuts corrects jusqu'à `paid` | ⬜ | À exécuter sur l'environnement de recette |
| R3 | Livraison complète | zone → rider → assign → out_for_delivery → deliver | Commande `served` après livraison | ⬜ | Couvert partiellement par `RestaurantDeliveryCycleTest` — recette terrain à faire |
| R4 | Livraison annulée | assign → cancel | Commande retourne `ready` ; livreur libéré | ⬜ | Couvert partiellement par `RestaurantDeliveryTest` — recette terrain à faire |
| R5 | Réservation + conflit | créneau libre → réserver → même table/date → 409 | Conflit refusé ; check-in OK | ⬜ | Couvert partiellement par `RestaurantReservationTest` — recette terrain à faire |
| R6 | No-show + rappel | réservation confirmée dépassée → job ; réservation J-1 → job | `no_show` unique ; rappel unique | ⬜ | Couvert partiellement par `RestaurantReservationJobsTest` — recette terrain à faire |
| R7 | Stock & COGS | décrément à la vente ; rapport COGS = recettes × coût moyen | Chiffres cohérents | ⬜ | Couvert partiellement par `RestaurantStockDecrementTest` / `RestaurantCogsTest` — recette terrain à faire |
| R8 | Fidélité | commande payée → points (une seule fois) ; échange > solde → 422 | Crédit exact ; jamais négatif | ⬜ | Couvert partiellement par `RestaurantLoyaltyTest` — recette terrain à faire |
| R9 | Promotions | code valide → remise bornée ; expiré/épuisé → 422 | Bornes respectées | ⬜ | Couvert partiellement par `RestaurantPromotionTest` — recette terrain à faire |
| R10 | Rapports & export | sales/kpis cohérents ; export CSV rejouable + URL signée valide 15 min | Mêmes chiffres que les données ; rejeu = même fichier | ⬜ | Couvert partiellement par `RestaurantReportTest` / `RestaurantExportTest` — recette terrain à faire |
| R11 | Sécurité & isolation | token autre tenant → 404 ; appel sans flag → 403 | Aucune fuite cross-tenant | ⬜ | Couvert partiellement par `RestaurantIsolationTest` / `RestaurantRbacMatrixTest` — recette terrain à faire |

## Règles de recette

1. Chaque scénario doit être exécuté sur l'environnement de recette (jamais sur prod).
2. Une ❌ bloque la recette : ouvrir une issue de correctif (label `BC-25 RESTAURANT`) et repasser le scénario après correctif.
3. La recette n'est **signée** (GO partiel) que lorsque tous les scénarios sont ✅.

## État de la couverture automatisée (2026-11-21, #9711)

**R1 est validé par la CI** : `RestaurantGoldenJourneyTest` passe (1 test, 23 assertions) et la suite
`tests/Feature/Restaurant` complète est verte (397 tests, 1 520 assertions, 0 échec).

**R2–R11 ne sont PAS validés** : les tests cités en colonne « Preuve » couvrent la mécanique
API de chaque domaine, mais **aucun n'est un scénario de recette terrain** (données réelles,
volumes réels, opérateur humain sur tablette). Une couverture unitaire n'est pas une recette.

Conséquence : le gate `recette` reste `pending` dans `dev-hub/tools/pilot-gates.json`.
La signature est une **action humaine** (chef de projet + restaurateur pilote) — un agent ne
peut ni exécuter la recette terrain ni signer à leur place.

## Signature

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | | | |
| Responsable technique | | | |
| Représentant métier (restaurateur pilote) | | | |

# Stock mobile — le mobile reste une OPTION

> Règle produit (issue #9275) : **toute opération de stock livrée en mobile
> doit exister en web** ; l'inverse n'est pas requis. Le web est la référence,
> le mobile est un confort — jamais une obligation.

## Pourquoi cette règle

Un écran mobile peut devenir, sans que personne ne le remarque, le **seul**
chemin d'une opération : un gérant qui ne se connecte qu'au téléphone finit
par considérer le mobile comme la norme, et le web se dégrade en silence.
Cette page fige l'inventaire des surfaces stock et la parité, pour que la
règle soit vérifiable et non un vœu.

## Surfaces stock

| Opération | Web (référence) | Mobile gérant restaurant | Mobile POS retail tablette |
| --- | --- | --- | --- |
| Consulter les niveaux | ✅ | ✅ `StockScreen` (onglet Niveaux) | ✅ `StockScreen` (onglet Niveaux) |
| Consulter les mouvements | ✅ | ✅ (onglet Mouvements) | ✅ (onglet Mouvements) |
| Voir les alertes de seuil | ✅ | ✅ (onglet Alertes) | ✅ (onglet Alertes) |
| Réceptionner (entrée) | ✅ | ✅ (saisie) | ✅ (saisie, offline-first) |
| Ajuster (delta signé) | ✅ | ✅ (saisie) | ✅ (saisie) |
| Déclarer une perte / casse | ✅ | ✅ (saisie) | ✅ (saisie) |
| Transfert inter-emplacements | ✅ | ⚠️ web uniquement | ⚠️ web uniquement |
| Valorisation du stock (coût moyen) | ✅ | ⚠️ web uniquement | ⚠️ web uniquement |

Légende : ✅ = disponible ; ⚠️ = **web uniquement** (le mobile ne l'expose
pas, et c'est assumé).

## Règle de garde

1. **Parité d'écriture** : toute opération de stock saisissable en mobile
   (réception, ajustement, perte) doit avoir un équivalent web documenté.
   Les transferts et la valorisation restent web-only — c'est un choix, pas
   un oubli.
2. **Pas d'endpoint mobile sans équivalent web** : un écran mobile de stock
   ne doit pas appeler un endpoint qui n'a pas de surface web. Si un tel
   endpoint apparaît, il faut soit créer la surface web, soit documenter ici
   pourquoi le mobile est le seul chemin (dérogation explicite).
3. **Aucune agrégation côté client** : les totaux (valorisation, marges)
   viennent du serveur. Un mobile qui somme localement diverge du web.

## Vérification

- **Checklist manuelle** (revue de PR touchant `features/stock/` ou
  `features/pos/`) : confirmer que chaque opération mobile a sa ligne dans le
  tableau ci-dessus et que la colonne « Web » est ✅.
- **Garde automatique** : `dev-hub/tools/check-mobile-stock-parity.sh`
  (à brancher dans `mobile-apps-guard`) échoue si un écran mobile de stock
  référence un endpoint sans surface web documentée dans ce fichier.

## Endpoints de stock et leur surface web

Chaque endpoint appelé par un écran mobile doit avoir une surface web
documentée (colonne « Web » ✅ ci-dessus). Liste vérifiée par
`check-mobile-stock-parity.sh` :

| Endpoint mobile | Surface web |
| --- | --- |
| `GET /retail/stock/levels` | ✅ web (niveaux) |
| `GET /retail/stock/movements` | ✅ web (journal) |
| `GET /retail/stock/alerts` | ✅ web (alertes) |
| `GET /restaurant/stock-levels` | ✅ web (niveaux) |
| `GET /restaurant/inventory-movements` | ✅ web (journal) |
| `GET /restaurant/stock/alerts` | ✅ web (alertes) |

## Historique

- **#9272** — socle partagé `leopardo_core` (modèles + repository + écran).
- **#9273** — écran stock du gérant restaurant.
- **#9274** — onglet stock de la caisse tablette Retail (offline-first).
- **#9275** — cette page et la règle de parité.
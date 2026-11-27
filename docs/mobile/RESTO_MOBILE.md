# Mobile RestaurantManager — apps serveur / livreur / gérant (RESTO-801..804, issues #6222..#6225)

> **Statut : contrats backend livrés + backend de support (sync offline 804) ;
> les apps Flutter se buildent dans la chaîne mobile (`front/mobile_apps`,
> `leopardo_core`).** Les contrats ci-dessous sont stables et testés
> (tests Feature `RestaurantMobile*ApiTest`).

## 801 — App mobile serveur (Flutter)

Surface API consommée par l'app (authentifiée Sanctum, tenant-scope) :

| Besoin | Endpoint |
|---|---|
| File de service | `GET /api/v1/restaurant/mobile/server/orders` |
| Plan de salle | `GET /api/v1/restaurant/mobile/server/tables` |
| Service d'une commande | `POST /api/v1/restaurant/mobile/server/orders/{id}/serve` |
| Encaissement cash (+ pourboire) | `POST /api/v1/restaurant/mobile/server/orders/{id}/pay` |
| Offline (804) | `POST /api/v1/restaurant/mobile/sync` (file idempotente) |

Invariants : montant vérifié serveur (PayOrderAction), transitions validées
par la machine à états (OrderStateMachine), RBAC identique au web
(`restaurant.order.update`, `restaurant.order.pay`), 404 sûr cross-tenant.

## 802 — App mobile livreur

Le livreur est résolu par `employee_id` (référence RH par valeur, RESTO-211) :

| Besoin | Endpoint |
|---|---|
| Tournées assignées | `GET /api/v1/restaurant/mobile/rider/deliveries` |
| Détail livraison (contact, adresse) | `GET /api/v1/restaurant/mobile/rider/deliveries/{id}` |
| Départ en livraison | `POST /api/v1/restaurant/mobile/rider/deliveries/{id}/out-for-delivery` |
| Livraison effectuée | `POST /api/v1/restaurant/mobile/rider/deliveries/{id}/deliver` |

Chaque transition publie un événement outbox `restaurant.delivery.*.v1`
(traçabilité + notifications client). Un livreur ne voit jamais une
livraison d'un autre tenant (404 sûr).

## 803 — App mobile gérant

| Besoin | Endpoint |
|---|---|
| KPIs du jour (CA, commandes, panier moyen, rotation tables) | `GET /api/v1/restaurant/mobile/manager/kpis` |
| Alertes de seuil de stock | `GET /api/v1/restaurant/mobile/manager/stock-alerts` |
| Session de caisse courante | `GET /api/v1/restaurant/mobile/manager/pos-sessions/current` |
| Clôture de caisse | `POST /api/v1/restaurant/mobile/manager/pos-sessions/{id}/close` |

Les indicateurs sont calculés côté serveur (jamais agrégés côté client) ; la
clôture délègue à `ClosePosSessionAction` (écart serveur + événement
`restaurant.pos.closed.v1`, RBAC principal/rh/manager).

## 804 — Synchronisation offline mobile (file idempotente)

`POST /api/v1/restaurant/mobile/sync` — l'app pousse ses opérations
effectuées hors ligne ; le serveur les rejoue IDEMPOTEMENT (clés client) :

```json
{ "operations": [
  { "type": "order.create", "idempotency_key": "cli-abc", "payload": { "branch_id": 1, "order_type": "takeaway" } },
  { "type": "order.add_item", "idempotency_key": "cli-def", "payload": { "order_id": 12, "product_id": 3, "quantity": 2 } },
  { "type": "order.pay", "idempotency_key": "cli-ghi", "payload": { "order_id": 12, "amount_minor": 3000 } }
] }
```

Réponse par opération : `created` | `duplicate` | `error` — **un rejeu ne
crée jamais de doublon** (critère d'acceptation, testé par
`RestaurantMobileSyncTest`). Borné à 50 opérations par appel.

## Note de build

- Apps Flutter : `front/mobile_apps/leopardo_*` — build/test dans la chaîne
  mobile CI (`mobile-apps-ci.yml`). Le gel 60 jours (FREEZE_SCOPE_60J.md)
  ne permet pas de NOUVELLES apps Flutter : les apps serveur/livreur/gérant
  seront intégrées aux apps existantes (leopardo_manager / nouvelles vagues)
  une fois le gate J60 levé, ou via exception fondateur.
- La synchronisation offline repose sur la file idempotente (804).

## Tests E2E Maestro — parcours commande grand public (#9996)

Le parcours de commande de `leopardo_public` (`RestaurantOrderScreen`) est
couvert par trois flows Maestro, sous
`front/mobile_apps/leopardo_public/maestro/restaurant/` :

| Fichier | Parcours |
|---|---|
| `01_fiche_et_menu.yaml` | Ouverture par lien profond, en-tête, menu, recherche, filtre catégorie |
| `02_panier_et_commande.yaml` | Ajout au panier, quantités, saisie client, validation |
| `03_paiement_et_suivi.yaml` | Choix du mode de paiement, paiement, suivi du statut |

**Pourquoi Maestro en plus des tests widget.** Les tests widget simulent le
serveur : ils ne voient ni le clavier logiciel qui masque le bouton de
validation, ni le défilement réel jusqu'au champ téléphone. Maestro pilote
l'app installée et voit ce que voit le client.

**Sélecteurs.** Aucun flow ne vise un texte traduit : tous utilisent les
`ValueKey` de `lib/features/journeys/domain/restaurant_test_keys.dart`. Un
libellé change de langue, une clé ne change pas. La garde
`test/maestro_flow_keys_test.dart` refuse un `id:` absent de cette classe, une
clé du parcours plus exercée, ou un tap sur un texte littéral — un renommage
casse donc `flutter test`, pas un émulateur dix minutes plus tard.

**Exécution.**

```bash
cd front/mobile_apps/leopardo_public
make e2e-check            # vérifie que Maestro est installé
make e2e-restaurant       # les 3 flows
make e2e-restaurant-smoke # parcours nominal seulement
```

Aucun identifiant d'établissement n'est codé dans les flows : `RESTO_SLUG` et
`RESTO_PRODUCT_CODE` viennent de l'environnement (défauts `le-bistrot` /
`demo-1`).

**CI.** Job `maestro-restaurant-e2e` dans `mobile-apps-ci.yml`, **non
bloquant** : un émulateur met 3 à 6 minutes à démarrer et le parcours dépend
d'un backend de démonstration joignable. En faire un check requis bloquerait
toutes les PR sur une infrastructure instable — l'incident que #9979 documente
(checks requis ABSENTS, aucune PR mergeable). Le budget de 89 workflows est
respecté : le job rejoint la chaîne mobile existante au lieu de créer un
fichier.

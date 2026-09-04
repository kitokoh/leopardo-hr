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

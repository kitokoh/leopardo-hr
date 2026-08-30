# Contrats sources — consommation de la livraison (BC-26 DELIVERY v0.2)

> DELIVERY-208 (#6299). Le module de livraison est **générique multi-tenant** :
> tout module consommateur (restaurant BC-25, retail BC-17, e-commerce BC-14,
> CRM BC-11) crée des livraisons via un **contrat unique**, jamais d'écriture
> directe inter-module en base, jamais de doublon.

## Contrat de création unique

`POST /api/v1/delivery/deliveries` (RBAC `delivery.permission:dispatcher|manager|admin`)

| Champ | Règle |
|---|---|
| `source` | `manual` \| `restaurant` \| `retail` \| `ecommerce` \| `crm` \| `field` |
| `source_reference` | **obligatoire hors `manual`** — référence de la commande côté source (`RST-…`, `POS-…`, id webhook, id commande CRM) |
| `idempotency_key` | UUID client, optionnel — doublon de défense en profondeur |

**Invariant** : unique `(company_id, source, source_reference)` — une commande
source = **une seule livraison**. Le rejeu (webhook e-commerce rejoué, retry
client, course concurrente 23505) retourne la livraison **existante** (200), il
n'en crée jamais une seconde.

Les commandes restent **propriétaires chez la source** : le module source ne
délègue que l'exécution (tournées, tracking, POD, COD) au moteur BC-26.

## Consommateurs

### BC-25 RESTAURANT (ratification au merge de #6236)

- Commandes `RST-…` → livraisons source `restaurant`, `source_reference = RST-…`.
- **Alignement** : les modèles `RestaurantDelivery*` conçus dans BC-25 ne
  dupliquent PAS le moteur BC-26 — BC-25 conserve les concepts restaurant
  (zones, livreurs restaurant, commandes), BC-26 exécute tournées / tracking /
  POD / COD. Issue de coordination dédiée au merge de #6236 (PR séparée).

### BC-17 RETAIL

- Ventes avec livraison (`POS-…`) → source `retail`, `source_reference = POS-…`.
- Le déclencheur vit côté vente (POS) ; la création passe par le contrat.

### BC-14 INTEGRATION (webhooks e-commerce)

- Réception webhook (inbox/outbox) → création source `ecommerce` avec
  `source_reference` = id de commande du marketplace.
- **Rejeu testé** : webhook rejoué → une seule livraison (idempotence par
  l'unique `(company_id, source, source_reference)`, défense 23505).

### BC-11 CRM

- Livraison de marchandise client → source `crm`, `source_reference` = id de la
  commande CRM, adresse du contact comme `dropoff_address`.

## Tests de référence

`DeliverySourceContractApiTest` (api/tests/Feature/Delivery/) :

- restaurant / retail / ecommerce / crm : création 201 + rejeu → même id (200) ;
- deux sources différentes, même référence → deux livraisons distinctes ;
- `manual` sans référence → chaque appel crée une nouvelle livraison ;
- isolation tenant : même `source_reference` sur un autre tenant → autre livraison.

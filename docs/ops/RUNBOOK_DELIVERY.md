# RUNBOOK — Livraison dernier-kilomètre (BC-26 DELIVERY)

> Module de livraison **générique multi-tenant** : agence, restaurant (BC-25),
> retail (BC-17), e-commerce (BC-14), CRM (BC-11). Spec :
> `docs/specifications/SOLUTION_DELIVERY.md`.

## Activation par tenant

- Feature flag `companies.features.delivery` → gate middleware `module.delivery`
  (kill switch). 403 `FEATURE_NOT_ENABLED` sinon.
- Registre MAT-001 : BC-26 `active` ; CODEOWNERS sur `api/app/Modules/Delivery/`.

## Endpoints (préfixe `/api/v1/delivery`)

| Méthode | Route | Rôle | Issue |
|---|---|---|---|
| GET | `/delivery/ping` | auth | DELIVERY-101 |
| GET/POST | `/delivery/deliveries` | manager | DELIVERY-201 |
| GET | `/delivery/deliveries/{id}` | manager | DELIVERY-201 |
| POST | `/delivery/deliveries/events` | manager* | DELIVERY-204 |
| POST | `/delivery/deliveries/{id}/tracking-link` | manager | DELIVERY-204 |
| GET | `/delivery/deliveries/{id}/tracking` | manager | DELIVERY-204 |
| POST | `/delivery/deliveries/routes` | manager | DELIVERY-202 |
| POST | `/delivery/deliveries/routes/{id}/assign` | manager | DELIVERY-202 |
| POST | `/delivery/deliveries/routes/{id}/close` | manager | DELIVERY-202 |
| GET | `/delivery/deliveries/routes/{id}` | manager | DELIVERY-202 |
| GET | `/delivery/deliveries/reports/summary` | manager | DELIVERY-207 |
| GET | `/delivery/deliveries/reports/export` | manager | DELIVERY-207 |
| GET | `/api/v1/deliveries/tracking/{token}` | **public** (token = credential) | DELIVERY-204 |

\* RBAC fin (`delivery.dispatcher/rider`) : BC-26-D05 (matrice différée).

## Invariants métier (machine à états `DeliveryStateMachine`)

- `created → assigned → picked_up → out_for_delivery → arrived → delivered`.
- `delivered` exige une POD (`proof_document_id`, BC-20 par valeur) —
  sinon 409 `PROOF_REQUIRED`.
- États terminaux (`delivered` / `returned` / `cancelled`) : aucune réouverture.
- Tournée : 1 livreur + 1 véhicule par date (index unique
  `delivery_routes_company_date_driver_unique`), clôture idempotente, refus
  `ROUTE_INCOMPLETE` si stops non terminés.

## Idempotence (rejeu mobile/edge)

- Événements : unique `(company_id, delivery_id, type, event_at)` +
  `idempotency_key` client → un rejeu retourne l'événement existant.
- Création de livraison source : unique `(company_id, source, source_reference)`
  → zéro doublon par commande source (webhook e-commerce rejoué = 1 livraison).

## Performance (MAT-014 `dev-hub/tools/performance-budgets.json`)

- `GET /delivery/deliveries/reports/summary` : **p95 ≤ 300 ms**, ≤ 8 requêtes
  (5 agrégations scopées `company_id`).
- Index requis : `delivery_deliveries (company_id, status, created_at)`
  (garde `check-performance-budgets.sh`).

## Observabilité & incidents fréquents

- **409 `INVALID_TRANSITION`** : événement hors séquence (ex. `delivered` sur
  une livraison `created`). Vérifier le statut actuel : `SELECT id, status
  FROM delivery_deliveries WHERE id = ?`.
- **409 `DRIVER_ALREADY_ASSIGNED`** : livreur déjà sur une tournée du même
  jour (contrainte métier, pas un bug).
- **404 `TRACKING_LINK_NOT_FOUND`** : token expiré (TTL 7 j) ou invalide —
  re-générer via `POST {delivery}/tracking-link`.
- **CSV export** : synchrone et streamé (léger) ; l'export async + observabilité
  des jobs est le scope BC-26-D07.
- Logs : chercher `delivery_` et `module=delivery` (corrélation `request_id`).

## Dimensions ouvertes

- BC-26-D05 : RBAC fin (`delivery.admin/dispatcher/rider/manager/reports`).
- BC-26-D07 : asynchronisme (exports, notifications).
- DELIVERY-203 : app mobile livreur (tournée du jour, POD upload BC-20,
  offline replay) — scope client Flutter.
- DELIVERY-205 : règlement COD + contrat BC-08 (posting idempotent).
- DELIVERY-206 : notifications destinataire via contrat BC-13 COMMS.
- DELIVERY-208 : contrats sources BC-25/17/14/11.

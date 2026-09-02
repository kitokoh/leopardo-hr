# Guide d'intégration partenaires — TravelAgency (TRAVEL-1006, issue #6119)

> **Collection Postman :** `dev-hub/postman/travel.postman_collection.json`
> **Contrat OpenAPI :** `api/openapi.yaml` (tag `Travel`, 1009+ opérations) — garde de couverture CI.

## 1. Transporteurs (API sortante — TRAVEL-806)

- **Abonnements** : `POST /api/v1/travel/webhook-subscriptions` (URL + secret
  de signature partagé, `travel.manage`).
- **Événements livrés** : `travel.booking.confirmed.v1`,
  `travel.booking.cancelled.v1`, `travel.payment.confirmed.v1`,
  `travel.payment.refunded.v1`, `travel.ticket.issued.v1`.
- **Signature** : en-têtes `X-Travel-Signature` (HMAC-SHA256) +
  `X-Travel-Timestamp` ; tolérance d'horodatage ±5 min ; rejeu idempotent
  (`event_id` unique).

## 2. Transporteurs (API entrante — TRAVEL-807)

- **Jeton** : `X-Carrier-Token` (délivré par le tenant, hash seul en base).
- **Sync trajets** : `POST /api/v1/travel/carrier-sync/trips` — upsert
  idempotent par `external_id` (routes + trajets + tarifs). Rejouable sans
  doublon.

## 3. Boutique publique (TRAVEL-1001/1002)

- **Jeton** : `X-Travel-Shop-Token` (rotation : `POST /api/v1/travel/public-shop-token/rotate`,
  le jeton en clair n'est retourné qu'à la rotation).
- **Tunnel** : recherche → réservation (`POST /api/v1/public/travel/shop/bookings`,
  `idempotency_key` obligatoire) → paiement (`POST /api/v1/public/travel/payments/initiate`)
  → callback signé → e-billet (`GET /api/v1/public/travel/tickets/{id}/pdf?code=`)
  → suivi (`GET /api/v1/public/travel/shop/bookings/{reference}?code=`).
- **Rate limiting** : `shop-public` (30 req/min/IP, configurable) + hook
  anti-bot CAPTCHA optionnel.

## 4. Règles transverses

- Montants en **unités mineures** entières ; devise de référence du tenant.
- `idempotency_key` obligatoire sur toute création (réservation, paiement).
- Codes d'erreur : 401 (auth), 403 (permission/flag), 404 (hors tenant,
  sûr), 409 (stock/conflit), 422 (validation), 410 (billet révoqué).
- 404 systématique cross-tenant (ne révèle jamais l'existence d'une
  ressource d'un autre tenant).

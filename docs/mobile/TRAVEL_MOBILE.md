# Mobile & portail client TravelAgency (TRAVEL-701/702, issues #6088/#6089)

> **Statut : contrats backend livrés + backend de support (703/704) ; les apps
> Flutter / portail web se buildent dans la chaîne mobile (`front/mobile_apps`,
> `leopardo_core`).** Les contrats ci-dessous sont stables et testés.

## 701 — App mobile agent/vendeur (Flutter)

Surface API consommée par l'app (toutes authentifiées Sanctum, tenant-scope) :

| Besoin | Endpoint |
|---|---|
| Vente guichet | `POST /api/v1/travel/bookings` (source `phone`, idempotente) |
| Confirmation comptant | `POST /api/v1/travel/bookings/{id}/confirm` |
| Check-in | `POST /api/v1/travel/tickets/{id}/check-in` |
| Encaissement cash | `POST /api/v1/travel/payments/initiate` (provider `cash`) |
| Manifeste | `GET /api/v1/travel/trips/{id}/manifest` |
| Caisse PDV | `POST /api/v1/travel/pdv/session/open|close`, `GET /.../current`, reçus |
| Offline (704) | `POST /api/v1/travel/mobile/sync` (file idempotente) |
| Push agents (703) | FCM via `PushNotificationService` (BC-13), événements `travel.booking.*.v1` |

Le widget Flutter utilise `leopardo_core` (pattern des apps existantes) ;
la synchronisation offline repose sur la file idempotente (704) — rejeu sans
doublon testé (`TravelMobileApiTest`).

## 702 — Portail client voyageur (web)

Surface publique consommée par le portail (jeton boutique, TRAVEL-1001) :

| Besoin | Endpoint |
|---|---|
| Recherche | `GET /api/v1/public/travel/shop/trips` |
| Détail | `GET /api/v1/public/travel/shop/trips/{id}` |
| Réservation | `POST /api/v1/public/travel/shop/bookings` |
| Paiement | `POST /api/v1/public/travel/payments/initiate` + callback signé |
| E-billet | `GET /api/v1/public/travel/tickets/{id}/pdf?code=` |
| Suivi | `GET /api/v1/public/travel/shop/bookings/{reference}?code=` |
| Annulation en ligne | `POST /api/v1/travel/bookings/{id}/cancel` (via agent, politique TRAVEL-813) |

## Note de build

- Apps Flutter : `front/mobile_apps/leopardo_*` — build/test dans la chaîne
  mobile CI (workflow `mobile-distribute-main.yml`).
- Le portail client (702) sera un front web dédié consommant les endpoints
  publics ci-dessus (contrats stables).

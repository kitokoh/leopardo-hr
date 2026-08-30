# Guide d'intégration partenaires — TravelAgency (BC-24 TRAVEL)

> TRAVEL-1006 (#6119) — Onboarding des intégrateurs externes : transporteurs
> (compagnies), passerelles de paiement et opérateurs du shop public.
> Spec : `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (§7 API v1, §8 Paiements & intégrations).

Ce guide décrit comment un partenaire (transporteur, passerelle de paiement)
s'intègre à la verticale TravelAgency. Tous les endpoints sont documentés dans
`api/openapi.yaml` (miroir : `dev-hub/openapi/v1.yaml`) et la collection Postman
`postman/leopardo_hr.postman_collection.json` (section « TravelAgency (BC-24) »).

---

## 1. Prérequis & environnement

- **Base URL** : `https://<domaine>/api/v1` (sandbox puis production).
- **Auth back-office** : `Authorization: Bearer <token Sanctum>` (session employé/agent).
- **Auth shop public** : jeton public signé par tenant (TRAVEL-1001) — voir §4.
- **Montants** : tous les montants sont en **minor units** (`price_minor`, entier).
  Ex. 15 000 FCFA → `15000`. Devise = code ISO 4217 du tenant (`XOF`, `XAF`, …).
- **Dates** : ISO 8601 (`2026-09-15T08:00:00+00:00`).

## 2. Parcours type d'un transporteur (compagnie)

### 2.1 Annuaire & référentiel

| Endpoint | Méthode | Rôle |
|---|---|---|
| `/travel/carriers` | GET/POST | manager |
| `/travel/classes` | GET/POST | manager |
| `/travel/vehicles` | GET/POST | manager |
| `/travel/routes` + `/travel/routes/{route}/stops` | GET/POST | manager |
| `/travel/trips` | GET/POST | manager |
| `/travel/trips/{trip}/prices` | GET/POST | manager |

### 2.2 Cycle de vente (guichet & shop)

1. **Recherche** : `GET /travel/trips/search?from_city_id=&to_city_id=&date=` (interne)
   ou `GET /travel/shop/trips` (shop public, token tenant).
2. **Réservation** : `POST /travel/bookings` (guichet) / `POST /travel/shop/bookings` (en ligne).
   - Champ `idempotency_key` obligatoire (réessai sûr).
3. **Paiement** : `POST /travel/bookings/{booking}/confirm` (comptant) ou le tunnel
   `POST /travel/payments/initiate` → callback provider (voir §3).
4. **Billets** : `POST /travel/bookings/{booking}/issue-ticket` → billets nominatifs
   (`ticket_number`, `status`), PDF via `GET /travel/tickets/{ticket}/pdf` (URL signée).
5. **Check-in** : `POST /travel/tickets/{ticket}/check-in`.
6. **Manifeste** : `GET /travel/trips/{trip}/manifest`.

Le parcours complet est verrouillé par le golden journey **GJ-TRAVEL-01**
(`dev-hub/tools/golden-journeys.json`).

## 3. Paiements — callbacks signés & idempotents

- `POST /travel/payments/initiate` — démarre un paiement (gateway par défaut :
  `cash` ; `pvit` en sandbox).
- `POST /travel/payments/callback` — **webhook provider** (hors auth utilisateur) :
  - **Signature** : en-tête `X-Signature` = HMAC-SHA256 du body brut avec la clé
    secrète du tenant (communiquée hors bande, rotation via
    `POST /travel/public-shop-token/rotate` côté shop).
  - **Idempotence** : le callback rejoue la même transaction sans effet de bord
    (clé : `payment_reference`).
- Vérification : re-calculer la signature et comparer (constant-time). Tout
  callback non signé → `401` ; référence inconnue → `404` ; état invalide → `422`.

## 4. Shop public (vente en ligne, TRAVEL-401..404 / 1001..1002)

Le shop expose une API en lecture + réservation **sans session utilisateur** :

| Endpoint | Méthode | Description |
|---|---|---|
| `/travel/shop/trips` | GET | Recherche publique (tenant résolu par jeton) |
| `/travel/shop/trips/{trip}` | GET | Détail + disponibilité |
| `/travel/shop/bookings` | POST | Réservation en ligne (expiration 15 min) |
| `/travel/shop/bookings/{reference}` | GET | Suivi par référence + code |

- **Jeton public** : généré côté admin (`GET /travel/public-shop-token`, rotation
  `POST /travel/public-shop-token/rotate`), transmis en en-tête `X-Tenant-Token`.
- Le jeton est **scopé au tenant** : aucune fuite cross-tenant possible
  (testé — TRAVEL-1001).
- Rate limiting renforcé + anti-bot sur les routes shop.

## 5. Webhooks sortants transporteurs (TRAVEL-806 — contrat cible)

L'agence notifie les transporteurs des événements de vente :

- **Contrat** : `POST https://<transporteur>/webhooks/travel/{event}` avec body
  JSON brut et en-tête `X-Leopardo-Signature: <HMAC-SHA256(body, secret)>`.
- **Événements** : `trip.published`, `trip.cancelled`, `booking.confirmed`,
  `ticket.issued`, `ticket.checked_in`, `trip.sync.requested`.
- **Retries** : réessais avec backoff (5 tentatives, délais croissants) puis
  dead-letter + alerte ; idempotence côté transporteur via `event_id`.
- **Réponse attendue** : `2xx` dans les 5 s ; tout autre statut déclenche un retry.

### 5.1 Synchronisation des trajets (TRAVEL-807 — contrat cible)

- API entrante : le transporteur pousse ses trajets via
  `POST /travel/inbound/trips/sync` (token partenaire + signature HMAC).
- Payload : `{ carrier_code, trip_code, origin, destination, departure_at,
  arrival_at, class, price_minor, currency, total_seats }`.
- **Idempotent** : rejeu d'un même `trip_code` met à jour sans dupliquer.

## 6. Erreurs & conventions

- Enveloppe Laravel : succès `{ "data": ... }`, pagination `{ "data": [...],
  "meta": {...} }`.
- Erreurs : `401` non authentifié, `403` permission, `404` introuvable
  (cross-tenant → 404, jamais 403), `409` conflit (ex. participation quiz
  dupliquée), `422` validation (`errors` structuré).
- **Idempotence** : les écritures critiques acceptent `idempotency_key`
  (bookings, paiements, exports) — rejouer la même requête rend le même résultat.

## 7. Collection Postman

`postman/leopardo_hr.postman_collection.json` → dossier **TravelAgency (BC-24)** :
référentiel & réseau, golden journey guichet, annonces payantes, quiz & sites
touristiques, contacts & formulaire, rapports. Variables : `baseUrl`, token
d'authentification (collection scope).

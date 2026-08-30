# RECETTE UAT — TravelAgency (BC-24 TRAVEL)

> **Issue :** TRAVEL-1010 (#6123) — Recette UAT du pilote TravelAgency (MAT-018, #5876).
> Prérequis : runbook `docs/ops/RUNBOOK_PILOT_TRAVELAGENCY.md` (activation + seed).

## R1 — Vente d'un billet en ligne (flux roi, GJ-TRAVEL-01)

1. Recherche : `GET /api/v1/travel/shop/trips?origin=&destination=&date=&class=&passengers=` → trajets publiés.
2. Détail trajet : `GET /api/v1/travel/shop/trips/{trip}` → prix, places restantes, horaires.
3. Réservation : `POST /api/v1/travel/shop/bookings` (idempotency_key) → `pending`, sièges réservés, `expires_at`.
4. Paiement : `POST /api/v1/travel/payments/initiate` → redirect/url provider ou comptant.
5. Callback : `POST /api/v1/travel/payments/callback` (signé) → `confirmed`.
6. Billet : `POST /api/v1/travel/bookings/{booking}/issue-ticket` → PDF + QR + statut `issued`.
7. Suivi : `GET /api/v1/travel/shop/bookings/{reference}` avec code de validation.
8. Embarquement : `POST /api/v1/travel/tickets/{ticket}/check-in` → `checked_in`.

**Critère :** rejeu du callback → 1 seule confirmation ; double issue-ticket → idempotent.

## R2 — Guichet (cash)

Réservation `booking_source=office` + paiement cash → confirmation immédiate + billet imprimable.

## R3 — Check-in & manifeste

Liste des passagers par trajet ; check-in individuel ; manifeste exportable.

## R4 — Annulation / remboursement

Annulation (motif obligatoire) → `cancelled` ; remboursement (réservé `travel.manage`) → `refunded`, audit tracé.

## R5 — Correspondances

Recherche multi-trajets à horaires compatibles (TRAVEL-809) → vente groupée liée.

## R6 — Multi-devise

Taux de conversion par tenant validés par période ; math entière sans perte d'arrondi (TRAVEL-805).

## R7 — Rapports & export

`GET /travel/reports/sales|occupancy|revenue|cancellations|dashboard` (permission `travel.reports`) ;
`POST /travel/reports/export` (idempotent) → URL signée éphémère ; read models recalculables (`travel:recalculate-read-models`, reprise = même état).

## R8 — Contenu éditorial

Articles (CRUD + modération draft/published/flagged), commentaires (modération + signalement), engagement (likes/partages/notes — unicité acteur, agrégats serveur).

## R9 — Sécurité & isolation

Cross-tenant → 404 sûr ; RBAC `travel.*` fail-closed ; payloads outbox redigés ; callback signé HMAC ; rejeu idempotent partout.

## Signatures

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (GO/NO-GO) | | | |
| Exploitant (recette) | | | |
| Sécurité (revue RGPD) | | | |

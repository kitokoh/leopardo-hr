# RECETTE UAT — TravelAgency (BC-24 TRAVEL)

> **Issue :** TRAVEL-1010 (#6123) — recette UAT du pilote TravelAgency (gate `recette`, MAT-018).
> **Mode d'emploi :** exécuter chaque scénario sur l'environnement de recette (tenant pilote activé), reporter ✅/❌ + preuve, puis faire signer le cahier par le chef de projet.

## Cahier de recette

| # | Scénario | Étapes clés | Attendu | Résultat | Preuve |
|---|---|---|---|---|---|
| R1 | Recherche de voyage (GJ-TRAVEL-01) | `GET /shop/trips` multi-critères (départ, arrivée, date, passagers) | Offres pertinentes, prix serveur | ⬜ | lien CI `TravelGoldenJourneyTest` (quand le module est sur main) |
| R2 | Réservation + paiement | `POST /shop/bookings` → `POST /payments/initiate` → callback signé | Réservation confirmée, idempotence callback | ⬜ | |
| R3 | Billet PDF | `POST /bookings/{id}/issue-ticket` → `GET /tickets/{id}/pdf` | PDF nominatif (#RCGV…, passager, prix), URL signée | ⬜ | |
| R4 | Check-in | `POST /tickets/{id}/check-in` | Statut billet → utilisé, une seule fois | ⬜ | |
| R5 | Annulation / remboursement | `POST /payments/{id}/refund` | Remboursement idempotent, événement tracé | ⬜ | |
| R6 | Location de véhicule | catalogue → réservation → validation | Disponibilité respectée | ⬜ | |
| R7 | Rapports & exports | ventes/billets, export CSV rejouable + URL signée | Chiffres cohérents | ⬜ | |
| R8 | Kill switch | flag off → `GET /travel/ping` | `403 FEATURE_NOT_ENABLED`, données intactes | ⬜ | |
| R9 | Backup/restore | dump schéma tenant → restore scratch | RPO < 24h / RTO < 4h (drill DR-24) | ⬜ | |
| R10 | Sécurité & isolation | token autre tenant → 404 ; PII passagers jamais dans les logs/événements | Aucune fuite | ⬜ | |

## Règles

1. Chaque scénario sur l'environnement de recette (jamais prod).
2. Une ❌ bloque : issue de correctif (label `BC-24 TRAVEL`) puis repasse.
3. Recette signée (GO partiel) seulement quand tous les scénarios sont ✅.

## Signature

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | | | |
| Responsable technique | | | |
| Représentant métier (agence pilote) | | | |

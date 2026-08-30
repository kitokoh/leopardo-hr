# Import legacy gv-back (TRAVEL-1003, issue #6116)

**Statut : livré (lot 3).** CLI `leopardo:travel:import-legacy {dump} --tenant=<uuid> [--dry-run]`.

## Format du dump (JSON)

```json
{
  "generated_at": "2026-01-15T10:00:00Z",
  "carriers":  [{ "code": "C1", "name": "Compagnie 1", "type": "company" }],
  "routes":    [{ "external_id": "R1", "code": "R1", "origin_city_code": "Douala", "destination_city_code": "Yaoundé" }],
  "trips":     [{ "external_id": "T1", "route_external_id": "R1", "carrier_code": "C1",
                  "departure_date": "2026-03-01", "departure_time": "08:00:00",
                  "arrival_date": "2026-03-01", "arrival_time": "12:00:00",
                  "total_seats": 40, "status": "scheduled",
                  "prices": [{ "class_code": "ECO", "adult_amount": 15000, "child_amount": 8000, "currency": "XAF" }] }],
  "bookings":  [{ "legacy_id": "B1", "trip_external_id": "T1", "status": "confirmed",
                  "total_amount": 15000, "currency": "XAF",
                  "passengers": [{ "full_name": "Jean Dupont", "age_category": "adult" }] }]
}
```

## Règles

- **Montants** : unités mineures entières (jamais de virgule flottante).
- **Enums** : `status` réservation mappé sur `pending|confirmed|cancelled|refunded`
  (défaut `confirmed`) ; `age_category` sur `infant|child|adult`.
- **Clés d'idempotence** : compagnie = `code` ; route = `external_id` ;
  trajet = `(company, carrier, external_id)` ; réservation = `legacy:{legacy_id}`
  → un rejeu ne duplique jamais rien.
- **Réservations** : statuts FIGÉS (historique), passagers recréés, aucune
  mutation de stock (les sièges ne sont pas générés pour l'historique).
- **Qualité** : les routes dont les villes sont introuvables (seed géo
  TRAVEL-1004 au préalable) sont rapportées `skipped`, jamais silencieuses.

# FuelStation — Modèle de données (FUEL-001..004)

> Référence du schéma tenant du module FuelStation. Toute table vit dans
> `shared_tenants`, `company_id` uuid NON nullable partout (isolation tenant
> `BelongsToCompany`, fail-closed #3727).

## Tables

### `fuel_stations` (#5796)
| Colonne | Contrainte |
|---|---|
| `code` | `UNIQUE (company_id, code)` |
| `status` | CHECK `active|inactive|maintenance|closed` |
| `timezone` / `currency` | défauts `UTC` / nullable |

Index tenant-first : `(company_id, status)`, `(company_id, created_at)`.
Clé `(id, company_id)` unique → FK composites des tables filles.

### `fuel_sites` (#5796)
FK composite `(station_id, company_id)` → `fuel_stations` (cascade) —
référence cross-tenant physiquement impossible. Statut CHECK `active|inactive`.

### `fuel_products` / `fuel_pumps` / `fuel_tanks` / `fuel_meters` / `fuel_equipment` (#5797)
- Unités CHECK `liter|kg|unit` (products, tanks, meters).
- `fuel_pumps.status` CHECK `active|inactive|maintenance|out_of_service`.
- `fuel_tanks.capacity` CHECK `> 0`.
- **Compteur actif UNIQUE par pompe** : index partiel
  `fuel_meters_active_per_pump_unique ON (company_id, pump_id) WHERE is_active`.
- FK composites conditionnelles `(site_id, company_id)` → `fuel_sites`
  (matérialisées sur environnements frais dès que #5796 est mergée).
- `fuel_equipment.type` CHECK `pump|tank|meter|nozzle|console|other`.

### `fuel_meter_readings` (#5798)
Relevé cumulé append-only (correction = nouvelle ligne liée, jamais UPDATE).

| Colonne | Contrainte |
|---|---|
| `reading_value` | CHECK `>= 0`, decimal(16,3) |
| `read_at` | timestamp UTC ; `read_at_local` chaîne horodatée du site |
| `delta` | calculé par `FuelMeterReadingService` (relevé effectif précédent) |
| `is_anomaly` / `anomaly_reason` | CHECK raison `decreasing_value|meter_replaced|out_of_range` |
| `is_rollover` | rollover explicite → pas d'anomalie, delta = valeur (cycle neuf) |
| `corrects_reading_id` | auto-référence (correction versionnée) |
| UNIQUE | `(company_id, meter_id, read_at)` — **zéro doublon** |

Index : `(company_id, meter_id, read_at)`, `(company_id, read_at)`,
`(company_id, operator_id)`, `(company_id, is_anomaly)`.
FK composite conditionnelle `(meter_id, company_id)` → `fuel_meters` (#5797).

## Invariants transverses
- Aucune ligne sans `company_id` ; aucun accès cross-tenant (scope global +
  FK composites).
- Toutes les mutations sensibles passent par le trait `Auditable`
  (`audit_logs`, #5439) — corrections tracées.
- Migrations additives/idempotentes (`schemaTableExists`), préfixes de
  séquence contrôlés par la garde #1962/#5437.

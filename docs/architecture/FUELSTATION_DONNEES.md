# FuelStation — Modèle de données (FUEL-001..008)

> Référence du schéma tenant du module FuelStation (solution verticale, flag
> `fuel_station` — activation #5795). Toute table vit dans `shared_tenants`,
> `company_id` uuid NON nullable partout (isolation tenant `BelongsToCompany`,
> fail-closed #3727). PK bigint (`$table->id()`), unités monétaires/volumes en
> entiers (jamais de flottants métier). Migrations additives/idempotentes
> (`schemaTableExists`), préfixes 000100-000403 contrôlés par la garde #1962.

## Tables

### `fuel_stations` (#5796, FUEL-002)
| Colonne | Contrainte |
|---|---|
| `code` | `UNIQUE (company_id, code)` ; `UNIQUE (id, company_id)` → FK composites |
| `status` | CHECK `active\|inactive\|archived` |
| `timezone` / `currency` | défauts `UTC` / nullable |
| `phone` / `metadata` | nullable ; `metadata` chiffré (cast `encrypted:array`, RGPD) |

Index tenant-first : `(company_id, status)`, `(company_id, created_at)`.

### `fuel_sites` (#5796, FUEL-002) — sites opérationnels d'une station
FK composite `(station_id, company_id)` → `fuel_stations` (cascade) — référence
cross-tenant physiquement impossible. Statut CHECK `active|inactive`.
`UNIQUE (company_id, code)` ; `metadata` chiffré.

### `fuel_pumps` / `fuel_tanks` / `fuel_meter_registers` (#5797, FUEL-003)
- FK composites `(station_id, company_id)` → `fuel_stations` (pompes/cuves) et
  `(pump_id, company_id)` → `fuel_pumps` (compteurs) — cross-tenant impossible.
- `fuel_pumps.status` CHECK `active|inactive|retired` ; `fuel_tanks.status` idem.
- `fuel_tanks.capacity_minor` CHECK `> 0` (capacité obligatoire, unités mineures).
- `fuel_meter_registers` : `meter_type` CHECK
  `mechanical|electronic|main_totalizer|secondary_totalizer|test` ; `status`
  CHECK `active|retired` ; `unit_code` CHECK `l|gal` ;
  `UNIQUE (company_id, pump_id, meter_code)` (un compteur par code et par pompe).
- Capacités/volumes/rollover en entiers (`capacity_minor`, `rollover_limit`),
  `precision_scale` pour les décimales natives.

### `fuel_meter_readings` / `fuel_meter_intervals` (#5798, FUEL-004)
Relevé cumulé append-only ; correction = nouvelle version (`source_code =
correction`), l'original passe `corrected` — jamais de UPDATE destructif.

| Colonne | Contrainte |
|---|---|
| `reading_value_minor` | entier, jamais de flottant métier |
| `captured_at_utc` / `captured_at_station_local` | heure UTC + locale (timezone station) |
| `source_code` | CHECK `operator\|import\|device\|correction` |
| `status` | `submitted\|accepted\|rejected\|corrected` |
| `idempotency_key` | `UNIQUE (company_id, idempotency_key)` — **zéro doublon au rejeu** |
| `fuel_meter_intervals.delta_minor` | delta entre deux relevés consécutifs ; décroissance → `anomaly` (revue obligatoire), rollover documenté |

FK composites `(meter_id, company_id)` → `fuel_meter_registers` (cascade),
`shift_id` → `fuel_shifts` (SET NULL). Index tenant-first sur
`(company_id, meter_id, captured_at_utc)`, `(company_id, pump_id, ...)`, `(company_id, status)`.

### `fuel_shifts` / `fuel_shift_assignments` (#5799, FUEL-005)
- Shift : créneau récurrent (nom, horaires début/fin, statut `active|inactive`),
  `station_id` FK composite → `fuel_stations`. `UNIQUE (company_id, name)`.
- Affectation : employé → shift pour une date ; `UNIQUE (company_id, shift_id,
  employee_id, assignment_date)` ; statut `scheduled|confirmed|completed|cancelled`.
- Chevauchement contrôlé au niveau application (`FuelShiftService::assertNoOverlap`).

### `fuel_cash_sessions` / `fuel_cash_session_movements` (#5801, FUEL-007)
- Session de caisse : ouverture/clôture, écarts, approbation manager.
  Clôture idempotente ; écritures d'audit (`FuelCashSessionClosed`).
- Mouvements : espèces/versements/retraits, montants en entiers.

### `fuel_sales` (#5802, FUEL-008)
- Ventes et transactions par pompe, liées shift/session, montants entiers.

## Invariants transverses
- Aucune ligne sans `company_id` ; aucun accès cross-tenant (scope global +
  FK composites `(id, company_id)` partout).
- Toute mutation sensible tracée (`audit_logs`, #5439).
- Releveurs de compteur idempotents par `idempotency_key` ; corrections
  versionnées et auditables ; anomalies jamais silencieuses (revue obligatoire).
- Solution inactive → 403 `FUEL_SOLUTION_INACTIVE` (fail-closed, middleware du
  module) ; routes documentées dans `api/routes/modules/fuel_station.php` sous
  `/fuel-station/...`.

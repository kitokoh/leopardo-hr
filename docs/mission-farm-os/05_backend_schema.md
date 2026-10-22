# Farm OS — Backend Schema (tables, colonnes, index, contraintes)

**Version :** 2.0 — 2026-10-18 · **Branche :** `bc/bc-37-farm-os`
**Conventions appliquees** : `company_id` NOT NULL partout · trait `App\Shared\Traits\BelongsToCompany` · prefixe de table `farm_` · montants/quantites en `numeric` controle (**jamais** de flottant binaire pour un calcul metier) · horodatage UTC + fuseau de l'exploitation · `down()` non destructeur · migrations idempotentes (`schemaTableExists`).

> Ce document decrit le schema **reellement en base**, verifie dans les 7 migrations tenant Farm OS.

---

## 1. Vue d'ensemble — 31 tables tenant

| Migration | Tables | Domaine |
|---|---|---|
| `2026_10_16_000100_8379` | `farm_sites`, `farm_activities`, `farm_capabilities`, `farm_teams`, `farm_team_members`, `farm_parcels`, `farm_crop_cycles`, `farm_operations` | fondations, parcellaire, interventions |
| `2026_10_17_000200_8554` | `farm_inputs`, `farm_input_lots`, `farm_input_stock_movements` | intrants & stock |
| `2026_10_18_000300_8701` | `farm_equipment`, `farm_equipment_usage_logs`, `farm_equipment_maintenances`, `farm_equipment_breakdowns`, `farm_equipment_documents` | materiel |
| `2026_10_18_000400_8702` | `farm_livestock_batches`, `farm_animals`, `farm_livestock_events`, `farm_animal_health_events`, `farm_reproduction_events`, `farm_production_records`, `farm_feed_records` | elevage |
| `2026_10_18_000500_8703` | `farm_storage_locations`, `farm_harvest_lots`, `farm_storage_movements` | recoltes & entreposage |
| `2026_10_18_000600_8704` | `farm_campaigns`, `farm_expenses`, `farm_sales_links` | finance |
| `2026_10_18_000700_8705` | `farm_sync_operations`, `farm_sync_conflicts` | hors ligne |

---

## 2. Fondations, parcellaire, interventions

### 2.1 `farm_sites` — exploitation / site

```text
farm_sites
- id                     bigint PK
- company_id             uuid NOT NULL  → companies(id)  INDEX
- name                   string NOT NULL
- reference              string NOT NULL      -- unique par tenant
- kind                   string NOT NULL      -- production | building | storage | other
- country_code           char(2)  NULL
- region                 string   NULL
- address                string   NULL
- latitude               decimal  NULL        -- nullable : jamais invente
- longitude              decimal  NULL
- timezone               string NOT NULL
- total_area_value       decimal  NULL
- total_area_unit        string   NULL        -- ha | m2 | acre
- total_area_source      string   NULL        -- declared | imported | measured
- status                 string NOT NULL
- metadata               jsonb    NULL        -- borne, jamais d'autorisation
- created_at/updated_at  timestampTz
- UNIQUE (company_id, reference)
- INDEX (company_id, status)
```

### 2.2 `farm_activities` — activites declarees (onboarding, multi-selection)

```text
farm_activities
- id              bigint PK
- company_id      uuid NOT NULL INDEX
- activity        string NOT NULL   -- FarmActivity (registre ferme, 16 cases)
- is_primary      boolean NOT NULL
- declared_at     timestampTz NOT NULL
- ended_at        timestampTz NULL  -- une activite peut etre arretee sans perte d'historique
- metadata        jsonb NULL
- UNIQUE (company_id, activity)     -- ajout idempotent, idempotent sur rejeu
```

### 2.3 `farm_capabilities` — outils actives par le tenant

```text
farm_capabilities
- id               bigint PK
- company_id       uuid NOT NULL INDEX
- capability       string NOT NULL  -- FarmCapability (registre ferme, 13 cases)
- enabled          boolean NOT NULL
- source           string NOT NULL  -- onboarding | settings | system
- decided_by       bigint NULL → employees(id)
- decided_at       timestampTz NOT NULL
- UNIQUE (company_id, capability)
```
> ADR-FARM-0001 : la modularite est portee ici, **pas** par 13 feature flags.

### 2.4 `farm_teams` / `farm_team_members` — equipes operationnelles

```text
farm_teams
- id, company_id, farm_site_id, name, lead_id, timestamps
- UNIQUE (company_id, name)

farm_team_members
- id, company_id, farm_team_id, employee_id, joined_at, left_at, timestamps
- UNIQUE (company_id, farm_team_id, employee_id)
```
> ADR-FARM-0007 : equipes adossees au socle RH — aucun second registre de personnes.

### 2.5 `farm_parcels` — parcelles

```text
farm_parcels
- id                bigint PK
- company_id        uuid NOT NULL INDEX
- farm_site_id      bigint NOT NULL → farm_sites(id)
- name              string NOT NULL
- reference         string NOT NULL
- area_value        decimal NULL
- area_unit         string  NULL          -- ha | m2 | acre
- area_source       string  NULL          -- declared | imported | measured
- geometry_wkt      text    NULL          -- nullable : aucune carte si absent
- geometry_source   string  NULL
- soil_type         string  NULL
- irrigation_type   string  NULL
- status            string  NOT NULL
- notes             text    NULL
- created_by        bigint  NULL
- timestamps
- UNIQUE (company_id, reference)
- INDEX (company_id, farm_site_id, status)
```

### 2.6 `farm_crop_cycles` — cycles culturaux

```text
farm_crop_cycles
- id, company_id, farm_parcel_id, crop_name, variety,
  planned_sowing_on, actual_sowing_on, planned_harvest_on, actual_harvest_on,
  status, notes, timestamps
- INDEX (company_id, parcel_id, status)
- INDEX (company_id, actual_sowing_on)
```

### 2.7 `farm_operations` — interventions agricoles

```text
farm_operations
- id, company_id, farm_site_id, farm_parcel_id, crop_cycle_id,
  operation_type, status, title, description,
  planned_start_at, planned_end_at, started_at, completed_at,
  assigned_to, farm_team_id, progress_percent, problem_reported_at, problem_note,
  client_operation_id, created_by, timestamps
- UNIQUE (company_id, client_operation_id)   -- idempotence terrain
- INDEX (company_id, status, planned_start_at)
- INDEX (company_id, parcel_id, planned_start_at)
- INDEX (company_id, assigned_to, status)
```

---

## 3. Intrants et stock

### 3.1 `farm_inputs` — catalogue d'intrants

```text
farm_inputs
- id, company_id, name, reference, category, unit, packaging,
  alert_threshold (decimal NULL), is_regulated (boolean), notes, timestamps
- UNIQUE (company_id, name)
- INDEX (company_id, category)
```
> `alert_threshold` **NULLABLE** : un intrant sans seuil declare n'apparait **jamais** dans les alertes.

### 3.2 `farm_input_lots` — lots

```text
farm_input_lots
- id, company_id, farm_input_id, lot_number, expiry_date (date NULL),
  quantity, supplier_id, received_at, timestamps
- UNIQUE (company_id, farm_input_id, lot_number)
- INDEX (company_id, expiry_date)
```
> `expiry_date` **NULLABLE** : tous les intrants ne periment pas.

### 3.3 `farm_input_stock_movements` — **journal immuable**

```text
farm_input_stock_movements
- id, company_id, farm_input_id, farm_input_lot_id,
  movement_type, quantity, quantity_after,
  farm_operation_id, parcel_id, reason, recorded_by,
  client_movement_id, occurred_at, timestamps
- UNIQUE (company_id, client_movement_id)
- INDEX (company_id, farm_input_id, occurred_at)
```
> Invariants : quantite **toujours positive** (le sens vient de `movement_type`) ; `quantity_after` est le cache derive ; aucun lot ne devient negatif.

---

## 4. Materiel agricole

### 4.1 `farm_equipment`

```text
farm_equipment
- id, company_id, farm_site_id, name, reference, kind, brand, model,
  serial_number, plate_number, acquired_on, acquisition_cost, currency,
  meter_type, meter_value, meter_source, status,
  assigned_team_id, assigned_employee_id, notes,
  client_equipment_id, created_by, timestamps
- UNIQUE (company_id, client_equipment_id)
- INDEX (company_id, status)
- INDEX (company_id, kind)
```
> `meter_type` peut valoir `none` (materiel sans compteur). `meter_source` ∈ {`manual`, `imported`} — **pas de case `sensor`**.

### 4.2 `farm_equipment_usage_logs` — **journal immuable**

```text
farm_equipment_usage_logs
- id, company_id, farm_equipment_id, meter_value, meter_delta, meter_source,
  farm_operation_id, parcel_id, operator_id, reason, recorded_by,
  client_usage_id, occurred_at, timestamps
- UNIQUE (company_id, client_usage_id)
- INDEX (company_id, farm_equipment_id, occurred_at)
```
> Un compteur ne recule pas : `FARM_EQUIPMENT_METER_REGRESSION`.

### 4.3 `farm_equipment_maintenances`

```text
farm_equipment_maintenances
- id, company_id, farm_equipment_id, maintenance_type, status, title, description,
  planned_at, performed_at, meter_value_at, cost_amount (NULL), currency,
  supplier_id, breakdown_id, notes, recorded_by, client_maintenance_id, timestamps
- UNIQUE (company_id, client_maintenance_id)
- INDEX (company_id, farm_equipment_id, status)
- INDEX (company_id, planned_at)
```

### 4.4 `farm_equipment_breakdowns`

```text
farm_equipment_breakdowns
- id, company_id, farm_equipment_id, severity, title, description,
  occurred_at, resolved_at, resolution_note, cost_amount (NULL), currency,
  recorded_by, client_breakdown_id, timestamps
- UNIQUE (company_id, client_breakdown_id)
- INDEX (company_id, farm_equipment_id, occurred_at)
```

### 4.5 `farm_equipment_documents`

```text
farm_equipment_documents
- id, company_id, farm_equipment_id, file_id, title, document_type,
  issued_on, expires_on, notes, recorded_by, timestamps
```
> `file_id` reference le service de fichiers **existant** — aucun stockage parallele.

---

## 5. Elevage

### 5.1 `farm_livestock_batches` — lots de cheptel

```text
farm_livestock_batches
- id, company_id, farm_site_id, name, reference, species, breed, sex,
  head_count (unsignedInteger), location, started_on, status, notes,
  client_batch_id, created_by, timestamps
- UNIQUE (company_id, client_batch_id)
```
> `head_count` = effectif **PRESENT**, cache derive du journal d'evenements. Effectif initial **ZERO**.

### 5.2 `farm_animals` — animaux identifies individuellement

```text
farm_animals
- id, company_id, farm_livestock_batch_id, farm_site_id, tag_number, name,
  species, breed, sex, birth_date (NULL), origin, location, status, notes,
  client_animal_id, created_by, timestamps
- UNIQUE (company_id, client_animal_id)
```
> `birth_date` **NULLABLE** : l'age n'est jamais invente.

### 5.3 `farm_livestock_events` — **journal immuable**

```text
farm_livestock_events
- id, company_id, farm_livestock_batch_id, farm_animal_id,
  event_type, quantity, head_count_after,
  occurred_at, reason, recorded_by, client_event_id, timestamps
- UNIQUE (company_id, client_event_id)
```
> 8 types : achat, naissance, transfert entrant, vente, mortalite, abattage, transfert sortant, reforme. Quantite **toujours positive**.

### 5.4 `farm_animal_health_events`

```text
farm_animal_health_events
- id, company_id, farm_livestock_batch_id, farm_animal_id,
  event_type, product_name, dosage, unit, administered_on,
  withdrawal_until (date NULL), next_due_on (date NULL),
  veterinarian, notes, recorded_by, client_health_id, timestamps
```
> `withdrawal_until` et `next_due_on` **NULLABLES** : aucune date n'est inventee.

### 5.5 `farm_reproduction_events`

```text
farm_reproduction_events
- id, company_id, farm_livestock_batch_id, farm_animal_id,
  event_type, occurred_on, method, gestation_days (NULL),
  expected_due_on (date NULL), outcome, offspring_count, notes,
  recorded_by, client_reproduction_id, timestamps
```
> `gestation_days` non saisie ⇒ **aucune** `expected_due_on` produite.

### 5.6 `farm_production_records`

```text
farm_production_records
- id, company_id, farm_livestock_batch_id, farm_animal_id,
  production_type, quantity, unit, quality_grade, recorded_on,
  notes, recorded_by, client_production_id, timestamps
```

### 5.7 `farm_feed_records`

```text
farm_feed_records
- id, company_id, farm_livestock_batch_id, farm_input_id,
  quantity, unit, cost_amount (NULL), currency, fed_on,
  notes, recorded_by, client_feed_id, timestamps
```

---

## 6. Recoltes et entreposage

### 6.1 `farm_storage_locations`

```text
farm_storage_locations
- id, company_id, farm_site_id, name, reference, location_type,
  capacity (NULL), capacity_unit, notes, client_location_id, created_by, timestamps
- UNIQUE (company_id, reference)
```

### 6.2 `farm_harvest_lots` — lots de recolte

```text
farm_harvest_lots
- id, company_id, parcel_id, crop_cycle_id, farm_site_id, farm_operation_id,
  lot_code, crop_name, quantity_harvested, unit, quality_grade,
  storage_location_id, harvested_at, quantity_remaining, status, notes,
  client_harvest_id, created_by, timestamps
- UNIQUE (company_id, client_harvest_id)
```
> `quantity_harvested` est **FIGEE** ; `quantity_remaining` est le cache derive du journal de mouvements.

### 6.3 `farm_storage_movements` — **journal immuable**

```text
farm_storage_movements
- id, company_id, farm_harvest_lot_id, movement_type, quantity, quantity_after,
  from_location_id, to_location_id, sale_document_id, sale_line_id,
  counterparty_id, unit_price, currency, reason, recorded_by,
  client_movement_id, occurred_at, timestamps
- UNIQUE (company_id, client_movement_id)
```
> `movement_type = sale` ⇒ `sale_document_id` **OBLIGATOIRE**. `transfer` ne retire rien du lot.
> Coherence verifiable : `dispatched + remaining == harvested`.

---

## 7. Finance

### 7.1 `farm_campaigns` — campagnes agricoles

```text
farm_campaigns
- id, company_id, name, start_year, starts_on, ends_on, status, notes,
  client_campaign_id, created_by, timestamps
- UNIQUE (company_id, client_campaign_id)
```

### 7.2 `farm_expenses` — **seule table financiere, DECLARATIVE**

```text
farm_expenses
- id, company_id, farm_site_id, parcel_id, crop_cycle_id,
  farm_livestock_batch_id, farm_equipment_id, farm_campaign_id,
  category, label, amount, currency, is_estimated (boolean), spent_on,
  supplier_id, purchase_document_id, farm_input_id, quantity, unit,
  file_id, notes, recorded_by, client_expense_id, timestamps
- UNIQUE (company_id, client_expense_id)
```
> 6 axes d'imputation. `is_estimated` distingue reel / estime. **Aucune** ecriture comptable produite.

### 7.3 `farm_sales_links` — **references** vers les documents de vente existants

```text
farm_sales_links
- id, company_id, sale_document_id, sale_line_id,
  farm_harvest_lot_id, farm_livestock_batch_id,
  quantity, unit, amount, currency, customer_id, sold_on, notes,
  recorded_by, client_sale_link_id, timestamps
- UNIQUE (company_id, client_sale_link_id)
```
> `sale_document_id` **OBLIGATOIRE** : Farm OS ne cree **aucun** document commercial. Un meme document ne peut pas etre impute deux fois au meme lot.

---

## 8. Hors ligne

### 8.1 `farm_sync_operations` — file de synchronisation

```text
farm_sync_operations
- id, company_id, client_operation_id, operation_type, payload_hash, payload (json),
  status, attempt_count, last_error, applied_entity_type, applied_entity_id,
  device_id, submitted_by, occurred_at, applied_at, timestamps
- UNIQUE (company_id, client_operation_id)
```
> `payload_hash` permet de distinguer un **rejeu identique** (rien a faire) d'un **conflit** (meme identifiant, contenu different).

### 8.2 `farm_sync_conflicts` — conflits conserves avec les DEUX valeurs

```text
farm_sync_conflicts
- id, company_id, farm_sync_operation_id, entity_type, entity_id,
  strategy, local_value (json), server_value (json), resolution,
  resolved_by, resolved_at, note, timestamps
```
> Les deux valeurs sont **toujours** conservees. Un conflit resolu ne peut pas etre re-resolu.

---

## 9. Permissions du manifest

| Permission | Role principal |
|---|---|
| `farm.admin` | proprietaire / administrateur |
| `farm.manager` | responsable d'exploitation, chef d'equipe |
| `farm.worker` | ouvrier agricole |
| `farm.stock` | responsable de stock |
| `farm.equipment` | responsable du materiel |
| `farm.livestock` | responsable d'elevage |
| `farm.sales` | responsable commercial |
| `farm.accounting` | comptable / gestionnaire |
| `farm.regulated_inputs` | sortie de produits reglementes |

Installees par `SolutionPermissionInstaller` via `employee_module_grants` — **aucun** nouveau systeme de roles.

---

## 10. Contrat API

- **83 routes** dans `api/routes/modules/farm_os.php`, toutes derriere `middleware('module.farm_os')` (fail-closed).
- **73 chemins** `/farm/*` documentes dans `api/openapi.yaml` (OpenAPI 3.0.3), lint redocly : **0 erreur**.
- Generateur idempotent : `api/scripts/gen_farm_openapi.py`.
- Garde de couverture : `dev-hub/tools/check-openapi-route-coverage.py` — `farm_os` n'apparait plus dans les routes non couvertes.

# CRM Client Leopardo — Modèle de données (V0)

> Document de référence du schéma tenant du CRM client (espaces client / API
> tenant). Complète `PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md` et
> `ADR-CRM-DUAL-CONTEXTS.md` (CRM-V0-01). Le CRM commercial Leopardo
> (`Platform`/`Marketing`) n'est pas couvert par ce document et reste inchangé.

## 1. Principes

- **Toutes les tables vivent dans le schéma tenant** (`shared_tenants`), jamais
  dans `public` : chaque ligne appartient à une entreprise (tenant).
- **`company_id` est `uuid` NON nullable sur chaque table.** L'isolation tenant
  est portée par le trait `App\Shared\Traits\BelongsToCompany` (scope global +
  auto-remplissage, garde fail-closed issue #3727) : une requête sans tenant sur
  la surface API tenant lève `TenantContextMissingException` (403).
- **Aucune relation cross-tenant n'est possible physiquement** : chaque
  extrémité d'une relation porte son propre `company_id` ; les clés étrangères
  ne relient jamais que des lignes créées sous le même tenant (le scope global
  impose le tenant sur les deux côtés à l'écriture).
- **PII protégée au repos** : `email`, `phone` et `metadata` sont en `text`
  avec cast Eloquent `encrypted` / `encrypted:array` (chiffrement Laravel au
  repos, RGPD loi 18-07). Le registre RGPD et la stratégie HMAC d'indexation
  déterministe sont traités par le chantier CRM-V0-09 (issue #5713).
- **États strictement contrôlés** : chaque colonne d'état porte une contrainte
  `CHECK` nommée (liste en §2) doublée par les allowlists de validation API
  (CRM-V0-07, issue #5711).
- **Migrations additives et idempotentes** (garde `schemaTableExists`), prefixes
  de séquence contrôlés par `dev-hub/tools/check-migration-basename-collisions.sh`.

## 2. Tables et invariants

### `crm_pipelines`

Canal de vente d'un tenant (ex. « Ventes directes », « Partenaires »).

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `name` | varchar(120) | `UNIQUE (company_id, name)` |
| `description` | text | nullable |
| `is_default` | boolean | défaut `false` |
| `is_active` | boolean | défaut `true` |
| `sort_order` | integer | défaut `0` |

Index : `(company_id, is_active)`.

### `crm_pipeline_stages`

Étape d'un pipeline. La probabilité attendue (`probability`) est bornée.

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `pipeline_id` | bigint FK | `crm_pipelines.id` `ON DELETE CASCADE` |
| `name` | varchar(120) | `UNIQUE (pipeline_id, name)` |
| `position` | integer | `UNIQUE (pipeline_id, position)` |
| `probability` | smallint | **CHECK `crm_pipeline_stages_probability_check` : `BETWEEN 0 AND 100`** |
| `is_active` | boolean | défaut `true` |
| `color` | varchar(20) | nullable |

Index : `(company_id, is_active)`.

### `crm_leads`

Prospect brut. PII chiffrée au repos.

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `first_name` / `last_name` | varchar(120) | NOT NULL |
| `email` / `phone` | text | nullable, **cast `encrypted`** |
| `company_name` | varchar(255) | nullable |
| `job_title` | varchar(120) | nullable |
| `source` | varchar(30) | défaut `manual`, **CHECK `crm_leads_source_check`** |
| `status` | varchar(30) | défaut `new`, **CHECK `crm_leads_status_check`** |
| `pipeline_id` | bigint FK | `crm_pipelines.id` `ON DELETE SET NULL` |
| `stage_id` | bigint FK | `crm_pipeline_stages.id` `ON DELETE SET NULL` |
| `owner_id` | bigint | nullable, index (employé du tenant) |
| `assigned_at` | timestamp | nullable |
| `expected_value` | decimal(15,2) | défaut `0` |
| `currency` | varchar(10) | nullable |
| `notes` | text | nullable |
| `metadata` | text | nullable, **cast `encrypted:array`** |
| `last_activity_at` | timestamp | nullable |
| `converted_at` | timestamp | nullable |

Contraintes CHECK :

- `crm_leads_status_check` : `status IN ('new','contacted','qualified','proposal','won','lost','junk')`
- `crm_leads_source_check` : `source IN ('manual','referral','website','social','email','call','event','partner','other')`

Index : `(company_id, status)`, `(company_id, owner_id)`, `(company_id, pipeline_id, stage_id)`,
`(company_id, created_at)`, `(company_id, last_activity_at)`.

### `crm_opportunities`

Affaire commerciale rattachée à un lead (optionnel) et/ou un contact.

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `name` | varchar(255) | NOT NULL |
| `lead_id` | bigint FK | `crm_leads.id` `ON DELETE SET NULL` |
| `contact_id` | bigint | nullable, index — référence logique vers `crm_contacts` (#5708) |
| `amount` | decimal(15,2) | défaut `0` |
| `currency` | varchar(10) | nullable |
| `status` | varchar(20) | défaut `open`, **CHECK `crm_opportunities_status_check`** |
| `pipeline_id` | bigint FK | `crm_pipelines.id` `ON DELETE SET NULL` |
| `stage_id` | bigint FK | `crm_pipeline_stages.id` `ON DELETE SET NULL` |
| `owner_id` | bigint | nullable, index |
| `expected_close_date` | date | nullable |
| `win_probability` | smallint | **CHECK `crm_opportunities_probability_check` : `BETWEEN 0 AND 100`** |
| `notes` | text | nullable |
| `metadata` | text | nullable, **cast `encrypted:array`** |
| `last_activity_at` / `closed_at` | timestamp | nullable |

Contraintes CHECK :

- `crm_opportunities_status_check` : `status IN ('open','won','lost')`
- `crm_opportunities_probability_check` : `win_probability BETWEEN 0 AND 100`

Index : `(company_id, status)`, `(company_id, owner_id)`, `(company_id, pipeline_id, stage_id)`,
`(company_id, expected_close_date)`, `(company_id, created_at)`, `(company_id, last_activity_at)`.

## 3. Stratégie d'indexation (anti N+1)

- Toutes les listes API filtrent d'abord par `company_id` (scope global) puis
  par un index composite adapté au filtre dominant (`status`, `owner_id`,
  `pipeline_id+stage_id`).
- Les timelines (`last_activity_at`, `created_at`) sont indexées par tenant
  pour les tris temporels (CRM-V0-06, issue #5710).
- Aucun `where` dynamique non indexé : les colonnes filtrables sont fermées par
  allowlist dans les requêtes (CRM-V0-07, issue #5711).

## 4. Cycle de vie

```
lead (new → contacted → qualified → proposal → won|lost|junk)
                                        │
                                        └─> opportunity (open → won|lost) [+ contact/account]
```

- Un lead `won` est converti en opportunity (CRM-V0-06, issue #5717) ; le lead
  conserve son historique et `converted_at` est posé.
- Les activités (notes, appels, emails) sont append-only et rattachées par
  `related_type`/`related_id` (CRM-V0-06, issue #5710).

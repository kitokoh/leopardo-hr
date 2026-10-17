# 06 — Backend Schema

**Base :** `main` @ `e9c5f986c` · **Vérification :** chaque table ci-dessous a été lue dans
`api/database/migrations`. Rien n'est présenté comme existant sans fichier source.
**Trois bases logiques :** `public` (73 migrations) · `tenant` schéma `shared_tenants`
(442 migrations) · `edge` SQLite (2 migrations).

Légende : `[existant]` = table/colonne présente sur `main` · `[à créer]` = proposition de
cette mission, aucune migration écrite dans ce lot.

---

## 1. Base `public` — plateforme

### 1.1 `companies` (tenant) — `[existant]`

```sql
CREATE TABLE companies (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name              varchar(100) NOT NULL,
  slug              varchar(100) NOT NULL UNIQUE,
  sector            varchar(100) NOT NULL,
  country           char(2)      NOT NULL,
  city              varchar(100) NOT NULL,
  address           text,
  email             varchar(150) NOT NULL UNIQUE,
  phone             varchar(30),
  logo_path         varchar(255),
  plan_id           integer NOT NULL REFERENCES plans(id),
  schema_name       varchar(63) NOT NULL UNIQUE,
  tenancy_type      enum('schema','shared') DEFAULT 'shared',
  status            enum('active','trial','suspended','expired') DEFAULT 'trial',
  subscription_start date NOT NULL,
  subscription_end   date NOT NULL,
  language          char(2) DEFAULT 'fr',
  timezone          varchar(50) DEFAULT 'Africa/Algiers',
  currency          char(3)  DEFAULT 'DZD',
  notes             text,
  created_at        timestamptz DEFAULT now(),
  updated_at        timestamptz DEFAULT now()
);
```

**Observations structurantes :**

- `sector` est `varchar(100)` **libre** — ce n'est pas une FK ni un CHECK. La validation
  vient de l'allowlist applicative (`SolutionCatalogue`), pas de la base. Une valeur forgée
  hors API resterait donc possible en écriture directe.
- Il n'existe **aucune** colonne `professional_profile`, `market_type`,
  `primary_site_id` ni `onboarding_state`. Les métadonnées libres vivent dans
  `companies.metadata` (jsonb, ajouté par migration ultérieure) — c'est là que
  `welcome_seen_at` et `setup_interview` sont stockés aujourd'hui.
- `email` est **unique globalement** : un même e-mail ne peut pas être manager de deux
  tenants. Contrainte réelle, à connaître avant tout parcours « rejoindre plusieurs
  entreprises ».

**Colonnes proposées `[à créer]`** (additives, nullables, sans réécriture des lignes) :

```sql
ALTER TABLE companies
  ADD COLUMN market_type        varchar(20),  -- international | domestic
  ADD COLUMN trade_profile      varchar(30),  -- importer|exporter|both|wholesaler|retailer
  ADD COLUMN onboarding_state   jsonb DEFAULT '{}'::jsonb, -- étape courante + horodatages
  ADD COLUMN primary_site_id    bigint;       -- FK logique vers shared_tenants.sites
-- CHECK market_type IN ('international','domestic') ou NULL (fail-closed applicatif)
```

### 1.2 Autres tables `public` réutilisées `[existant]`

| Table | Rôle mission |
|---|---|
| `super_admins` | comptes plateforme (email, password, rôle, 2FA) |
| `plans`, `subscriptions`, `invoices` | abonnement et facturation |
| `company_solutions` | solutions activées par tenant (`solution_code`, statut) |
| `company_features` | kill-switch de fonctionnalité par tenant |
| `user_invitations` | invitations collaborateur (`metadata` jsonb pour pré-affectations) |
| `platform_outbox_events` | outbox plateforme (consumers, replay) |
| `audit_logs` | journal d'action (`actor`, `action`, `subject`, `metadata`) |
| `trial_signups` | inscriptions en attente d'OTP |
| `supported_countries` (config) | allowlist pays/devise/fuseau |

---

## 2. Base `tenant` — schéma `shared_tenants`

Toute table porte `company_id uuid` + scope Eloquent `BelongsToCompany`. Les tables
ci-dessous sont celles que la mission exploite ; les 442 migrations du schéma couvrent
l'ensemble des modules.

### 2.1 `sites` — établissement générique `[existant]`

```sql
CREATE TABLE sites (
  id            serial PRIMARY KEY,
  company_id    uuid,
  name          varchar(100) NOT NULL,
  address       text,
  gps_lat       numeric(10,8),
  gps_lng       numeric(11,8),
  gps_radius_m  smallint DEFAULT 100,
  created_at    timestamptz DEFAULT now()
);
```

**Constat critique :** ni index sur `company_id`, ni `updated_at`, ni type d'établissement,
ni statut, ni horaires, ni `code`. Le filtre par établissement est donc **applicatif** et
non garanti par le schéma → base du gap T-01.

**Colonnes proposées `[à créer]` :**

```sql
ALTER TABLE sites
  ADD COLUMN code        varchar(30),
  ADD COLUMN site_type   varchar(30),   -- restaurant|station|store|school|office|…
  ADD COLUMN timezone    varchar(50),
  ADD COLUMN currency    char(3),
  ADD COLUMN status      varchar(20) DEFAULT 'active',
  ADD COLUMN updated_at  timestamptz;
CREATE INDEX sites_company_id_idx ON sites (company_id);
CREATE UNIQUE INDEX sites_company_code_uidx ON sites (company_id, code) WHERE code IS NOT NULL;
```

### 2.2 Onboarding `[existant]`

```sql
CREATE TABLE onboarding_steps (
  id           bigserial PRIMARY KEY,
  company_id   uuid NOT NULL,
  step_key     varchar(50) NOT NULL,
  title        varchar(200) NOT NULL,
  description  text,
  status       enum('pending','in_progress','completed','skipped') DEFAULT 'pending',
  completed_at timestamptz,
  completed_by integer,
  "order"      smallint DEFAULT 0,
  required     boolean DEFAULT true,
  metadata     jsonb DEFAULT '{}',
  created_at   timestamptz, updated_at timestamptz,
  UNIQUE (company_id, step_key)
);
```

Le couple `(company_id, step_key)` unique est ce qui rend `SeedDefaultSteps` **idempotent**
(#4188). Toute nouvelle étape doit réutiliser cette clé, jamais ajouter une table.

### 2.3 Employés, rôles, grants `[existant]`

```sql
employees            (id, company_id, user_id, first_name, last_name, email, phone,
                      position_id, department_id, site_id, manager_role, status, …)
employee_module_grants (id, company_id, employee_id, module_key, granted_by, granted_at)
roles / permissions / role_permissions   -- Spatie, guard `web` / portail
```

`manager_role ∈ {principal, rh, dept, superviseur}` — **rôle de gestion RH**, distinct des
rôles métier de verticale, qui sont installés par `SolutionPermissionInstaller`.

### 2.4 Edge / synchronisation `[existant]`

```sql
CREATE TABLE edge_nodes (
  id uuid PRIMARY KEY, company_id uuid, site_id bigint,
  name varchar, status varchar, last_seen_at timestamptz,
  pairing_code varchar, revoked_at timestamptz, …
);

CREATE TABLE sync_queue (
  id uuid PRIMARY KEY,
  edge_node_id uuid NOT NULL REFERENCES edge_nodes(id) ON DELETE CASCADE,
  entity_type  varchar NOT NULL,
  entity_id    varchar NOT NULL,
  operation    varchar NOT NULL,           -- create|update|delete
  payload      jsonb   NOT NULL,
  status       varchar DEFAULT 'pending',  -- pending|processing|synced|conflict|failed
  attempt_count integer DEFAULT 0,
  conflict_resolution varchar,             -- local_wins|cloud_wins|manual
  conflict_note text,
  synced_at timestamptz, created_at, updated_at
);

CREATE TABLE sync_logs (
  id, company_id, edge_node_id, direction, entity_type,
  conflicts_detected int, conflicts_resolved int, payload/erreur, created_at
);
```

**Constat :** `sync_queue` et `sync_logs` existent en **double** — schéma `tenant` (source
serveur) et base `edge` SQLite (source locale). Les deux définitions doivent rester
alignées ; aucune garde automatique ne le vérifie aujourd'hui (gap F-02).

**Colonnes proposées `[à créer]`** pour le diagnostic terrain :

```sql
ALTER TABLE sync_queue
  ADD COLUMN idempotency_key varchar(120),  -- clé stable côté client
  ADD COLUMN last_error      text,
  ADD COLUMN last_attempt_at timestamptz,
  ADD COLUMN next_retry_at   timestamptz;
CREATE UNIQUE INDEX sync_queue_idem_uidx
  ON sync_queue (edge_node_id, idempotency_key)
  WHERE idempotency_key IS NOT NULL;
```

L'index unique partiel est ce qui **prouve** l'idempotence côté base plutôt que de la
confier au seul code client (exigence OFF-04).

### 2.5 Ventes, produits, tâches `[existant]`

| Domaine | Tables principales |
|---|---|
| Restaurant | `restaurant_branches`, `restaurant_products`, `restaurant_orders`, `restaurant_order_items`, `restaurant_ingredients`, `inventory_movements`, `restaurant_branch_staff` |
| Station | `fuel_sites`, `fuel_products`, `fuel_tanks`, `fuel_sales`, `fuel_cash_sessions`, `fuel_meter_intervals` |
| Travel | `travel_offices`, `travel_trips`, `travel_trip_seats`, `travel_bookings`, `travel_tickets` |
| RH | `employees`, `attendance_logs`, `absences`, `schedules`, `payroll_*` |
| Planning | `tasks`, `task_assignments` |
| Documents | `documents`, `employee_documents`, `showcase_*` |

`fuel_sales` porte désormais `payment_method` + `payment_reference` (PR #8615, #8579) et un
statut `cancelled` pour l'annulation compensatoire — la ligne n'est **jamais** supprimée.

---

## 3. Modèle de données cible — ce qui manque

| Objet | Statut | Justification |
|---|---|---|
| `companies.market_type` / `trade_profile` / `primary_site_id` / `onboarding_state` | à créer | Porter l'intention Trade jusqu'au provisioning (ONB-02/04) |
| `sites` : `code`, `site_type`, `status`, `timezone`, `currency`, index | à créer | Établissement réellement exploitable (TEN-02) |
| `onboarding_drafts` (pré-login) | à créer **si** la reprise pré-OTP est retenue | Sinon : conserver `companies.metadata` et assumer l'absence de reprise avant OTP (ADR-05) |
| `sync_queue.idempotency_key` + index unique | à créer | Prouver l'idempotence du rejeu (OFF-04) |
| `integration_credentials` (registre générique) | à créer **si** SUP-05 est implémenté | Sinon : exposer un registre en lecture dérivé de la config |
| Toute autre table | — | **Aucune** : la mission comble des écarts, elle n'ajoute pas de modèle parallèle |

**Règle de migration (garde du dépôt) :** une table = une migration, préfixe date-heure,
`down()` non destructif, colonnes miroir dans `CreatesMvpSchema` pour les tables tenant
(garde #5443), aucune collision de nom de fichier entre `public/` et `tenant/`.

---

## 4. Index et contraintes à ajouter (priorité)

| Table | Index / contrainte | Motif |
|---|---|---|
| `sites` | `(company_id)` | toutes les requêtes filtrent par tenant |
| `sites` | `(company_id, code) UNIQUE WHERE code IS NOT NULL` | identifiant d'établissement stable |
| `companies` | `(country, status)` | filtres console superadmin |
| `companies` | `(market_type)` partiel | segmentation Trade |
| `sync_queue` | `(edge_node_id, status)` **existant** | déjà présent |
| `sync_queue` | `(edge_node_id, idempotency_key) UNIQUE` partiel | idempotence du rejeu |
| `onboarding_steps` | `(company_id, step_key)` **existant** | idempotence du seed |
| `employee_module_grants` | `(company_id, employee_id, module_key) UNIQUE` | éviter les doublons de grant |

---

## 5. Rétention et confidentialité

| Donnée | Rétention | Accès |
|---|---|---|
| `audit_logs` | 24 mois | plateforme + tenant concerné |
| `sync_logs` | 90 jours | tenant + support (agrégats) |
| `trial_signups` non vérifiés | 30 jours puis purge | plateforme |
| Données métier client | durée du contrat | tenant uniquement |
| Impersonation | traces conservées, session bornée | plateforme, journalisée |

Le superadmin ne lit **aucune** table métier d'un tenant sans action explicite,
permissionnée et journalisée (SUP-09).

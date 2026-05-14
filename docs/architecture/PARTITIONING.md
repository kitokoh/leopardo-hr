# Strategie de partitionnement et scalabilite — Leopardo RH

> Derniere mise a jour : 2026-05-14

---

## 1. Contexte

Leopardo RH est une application SaaS multi-tenant. Chaque entreprise cliente (tenant) a ses propres donnees isolees. La strategie de partitionnement doit supporter :

- **Isolation des donnees** entre tenants (obligatoire, reglementaire)
- **Scalabilite horizontale** a mesure que le nombre de tenants et d'employes croit
- **Performance** pour les operations lourdes (calcul de paie, rapports, exports)
- **Maintenance** simplifiee (migrations, sauvegardes, restauration par tenant)

## 2. Modele actuel : Schema-based multi-tenancy

### Architecture

```
PostgreSQL Instance
├── public schema          # Tables partagees (users, companies, plans, features, languages)
├── tenant_{company_id}    # Schema par tenant
│   ├── employees
│   ├── attendance_logs
│   ├── payroll_runs
│   ├── pay_slips
│   ├── absences
│   ├── contracts
│   └── ... (toutes les tables metier)
```

### Mecanisme

1. **`TenantMiddleware`** detecte le tenant courant (via token JWT → `company_id`)
2. Le middleware execute `SET search_path TO tenant_{id}, public`
3. Toutes les queries utilisent automatiquement le schema tenant
4. **`BelongsToCompany`** trait ajoute un Global Scope `company_id` comme filet de securite
5. En fin de requete : `resetToPrevious()` dans un bloc `try/finally`

### Avantages

- Isolation forte au niveau PostgreSQL (schema separation)
- Possibilite de backup/restore par tenant
- Pas de risque de fuite de donnees inter-tenants
- Performance : indexes par schema, pas de WHERE company_id sur toutes les queries

### Limites actuelles

- Nombre de schemas PostgreSQL : performant jusqu'a ~1000-5000 tenants
- Les migrations doivent etre executees sur CHAQUE schema tenant
- Le `search_path` est un etat de session (attention aux workers persistants)

## 3. Strategie de partitionnement par table

### Tables candidates au partitionnement natif PostgreSQL

Les tables a forte volumetrie beneficient du **partitionnement declaratif** PostgreSQL :

| Table | Cle de partition | Type | Raison |
|-------|-----------------|------|--------|
| `attendance_logs` | `checked_at` (mois) | RANGE | Volume eleve, queries toujours par periode |
| `payroll_runs` | `period_start` (mois) | RANGE | Calculs mensuels, rapports par periode |
| `pay_slips` | `period_start` (mois) | RANGE | Volume proportionnel aux employes x mois |
| `audit_logs` | `created_at` (mois) | RANGE | Volume tres eleve, retention limitee |
| `webhook_deliveries` | `created_at` (mois) | RANGE | Volume lie aux evenements, purge reguliere |
| `ai_audit_logs` | `created_at` (mois) | RANGE | Logging IA, croissance rapide |

### Implementation

```sql
-- Exemple : partitionnement attendance_logs par mois
CREATE TABLE attendance_logs (
    id BIGSERIAL,
    company_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    checked_at TIMESTAMPTZ NOT NULL,
    -- ... autres colonnes
    PRIMARY KEY (id, checked_at)
) PARTITION BY RANGE (checked_at);

-- Partitions mensuelles (creation automatisee par cron)
CREATE TABLE attendance_logs_2026_01 PARTITION OF attendance_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE attendance_logs_2026_02 PARTITION OF attendance_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### Automatisation

Un job schedule Laravel cree les partitions futures (3 mois d'avance) :

```php
// app/Console/Commands/CreateMonthlyPartitions.php
// Execute mensuellement via le scheduler
```

## 4. Indexation

### Indexes obligatoires (toutes tables metier)

```sql
-- Chaque table tenant doit avoir au minimum :
CREATE INDEX idx_{table}_company_id ON {table} (company_id);
CREATE INDEX idx_{table}_created_at ON {table} (created_at);
```

### Indexes specifiques par module

| Table | Index | Type | Usage |
|-------|-------|------|-------|
| `employees` | `(company_id, status)` | B-tree | Filtrage actifs/archives |
| `attendance_logs` | `(employee_id, checked_at)` | B-tree | Rapports mensuels |
| `payroll_runs` | `(company_id, status, period_start)` | B-tree | Recherche runs par periode |
| `absences` | `(employee_id, start_date, end_date)` | B-tree | Chevauchement conges |
| `audit_logs` | `(auditable_type, auditable_id)` | B-tree | Historique par entite |
| `vehicles` | `(company_id, tracker_id)` | B-tree unique | Lookup vehicule par tracker |

### Slow query monitoring

- Activer `log_min_duration_statement = 500` en staging
- Les queries > 500ms sont loguees et traitees comme des incidents performance
- Voir `docs/PLAN_ACTION/07_MONITORING_LOGGING_OBSERVABILITE.md` pour le plan monitoring

## 5. Strategie de scalabilite

### Phase 1 — Actuelle (1-500 tenants)

- Schema-based multi-tenancy sur une seule instance PostgreSQL
- Redis pour le cache et les sessions
- Queue workers pour les jobs asynchrones (paie, PDF, exports)
- Suffisant pour la majorite des cas d'usage PME

### Phase 2 — Croissance (500-5000 tenants)

- **Read replicas PostgreSQL** pour les rapports et analytics
- **Connection pooling** via PgBouncer
- Partitionnement par table pour les tables a forte volumetrie
- **Cache aggressif** Redis sur les endpoints read-heavy (dashboard, metriques)

### Phase 3 — Scale (5000+ tenants)

- **Sharding par tenant** : groupes de tenants sur des instances PostgreSQL separees
- Routeur de tenant au niveau middleware
- Chaque shard = instance PostgreSQL + Redis dedies
- Migration de tenant entre shards sans downtime

```
Load Balancer
    ├── App Server Pool
    │   └── TenantRouter Middleware
    │       ├── Shard 1 (tenants 1-1000)     → PG instance A + Redis A
    │       ├── Shard 2 (tenants 1001-2000)   → PG instance B + Redis B
    │       └── Shard 3 (tenants 2001-3000)   → PG instance C + Redis C
    └── Shared Services
        ├── Central PG (users, companies, billing, plans)
        └── Central Redis (sessions, feature flags)
```

## 6. Backup et retention

| Donnee | Frequence backup | Retention | Strategie |
|--------|-----------------|-----------|-----------|
| Schemas tenants | Quotidien | 30 jours | `pg_dump` par schema |
| Schema public | Quotidien | 90 jours | `pg_dump` schema public |
| Audit logs | Archivage mensuel | 7 ans (conformite) | Export S3 + suppression partitions anciennes |
| Webhook deliveries | Purge mensuelle | 90 jours | Suppression partitions > 90j |
| AI audit logs | Purge mensuelle | 180 jours | Export avant purge |

Voir `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` pour les procedures detaillees.

## 7. Contraintes reglementaires

- **Loi 18-07 (Algerie)** : donnees personnelles stockees localement ou dans des juridictions autorisees
- **RGPD (France/UE)** : droit a l'effacement, portabilite, minimisation
- Le partitionnement par schema facilite la conformite : suppression complete d'un tenant = `DROP SCHEMA tenant_{id} CASCADE`

## 8. References

- [MULTITENANCY.md](./MULTITENANCY.md) — Detail de l'implementation multi-tenant
- [C4_ARCHITECTURE.md](./C4_ARCHITECTURE.md) — Vue architecturale C4
- [RUNBOOK_BACKUP_RESTORE.md](../GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md) — Procedures backup
- [AGENTS.md](../../AGENTS.md) — Pieges connus TenantMiddleware

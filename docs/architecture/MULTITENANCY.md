# Multi-Tenancy Strategy

Leopardo RH uses a sophisticated hybrid multi-tenancy model to support thousands of small businesses while providing high-end isolation for enterprise customers.

## 🚀 Hybrid Isolation Model

| Feature | Shared Mode (Starter/Business) | Schema Mode (Enterprise) |
|---------|-------------------------------|--------------------------|
| **Isolation** | Logical (Row-level) | Physical (Schema-level) |
| **Database** | Shared PostgreSQL Schema | Dedicated PostgreSQL Schema |
| **Performance** | High (connection pooling) | Maximum (isolated indexes/buffers) |
| **Scalability** | Easy to manage | Maximum isolation & regulatory compliance |

## 🛠 Implementation Details

### The Tenant Middleware
The `TenantMiddleware` is the brain of our tenancy system. It identifies the tenant from the authenticated user and configures the environment accordingly.

```php
// Identification flow
$lookup = UserLookup::where('email', $user->email)->firstOrFail();
$company = Company::findOrFail($lookup->company_id);

if ($company->tenancy_type === 'schema') {
    // physical isolation
    DB::statement("SET search_path TO {$company->schema_name}, public");
} else {
    // logical isolation
    DB::statement("SET search_path TO shared_tenants, public");
}
```

### Global Scopes (Shared Mode)
For tenants in shared mode, we use a `BelongsToCompany` trait that automatically applies a global scope to all queries, ensuring data leakage is impossible.

```php
static::addGlobalScope('company', function (Builder $builder) {
    if (app()->bound('current_company')) {
        $builder->where('company_id', app('current_company')->id);
    }
});
```

## 🔄 Live Migration (Shared to Schema)

Leopardo RH supports zero-downtime upgrades from Shared to Enterprise mode. The `TenantMigrationService` handles:
1. Snapshotting data.
2. Creating a dedicated PostgreSQL schema.
3. Migrating rows while stripping `company_id`.
4. Updating the tenant lookup registry.
5. Verifying data integrity.

## 📊 Database Organization

```text
leopardo_db
├── public              (System-wide: plans, companies, lookups)
├── shared_tenants      (SME logical isolation)
├── company_a1b2...     (Enterprise A physical isolation)
└── company_c3d4...     (Enterprise B physical isolation)
```

---

For technical specifications, see [ERD Details](../dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md).

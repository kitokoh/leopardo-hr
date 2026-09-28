# Multi-Tenancy Strategy — Leopardo

Leopardo is built from the ground up as a native multi-tenant SaaS. Our architecture ensures that customer data remains strictly isolated, whether you are a small startup or a large enterprise with strict compliance requirements.

## 🏗 The Isolation Model — Shared Schema (definitive)

> **Decision:** the shared schema is the **only** tenancy model — see ADR [0027](adr/0027-shared-schema-definitif.md) (BOS-005, #8203), which amends ADR [0001](adr/0001-multi-tenant-postgresql.md). The dedicated-schema mode is locked: creating such a tenant is refused (`Company::booted()` → 422 `COMPANY_SCHEMA_MODE_LOCKED`).

-   **Storage:** all tenants share the PostgreSQL schema `shared_tenants` (platform registry tables live in `public`).
-   **Isolation Mechanism:** every business table includes a `company_id` column.
-   **Enforcement:** Global Query Scopes in Laravel (`BelongsToCompany`) automatically filter every query by the authenticated tenant's ID; the tenant always comes from the session, never from user input.
-   **Runtime switching:** `TenantManager` is the single entry point — `withinTenant()` for a full tenant context, `withinSearchPath()` for raw `search_path` scopes with guaranteed restoration (BOS-019, #8204). Manual `SET search_path` statements outside `TenantManager` are rejected in review; `EnsureKioskSearchPathReset` (#3368) remains as a safety net.

### Legacy dedicated-schema mode (locked, not supported)

-   The remaining `tenancy_type = 'schema'` code paths form a **bounded inventory** (historical guard + legacy-row protections) documented in ADR 0027 — they are not a supported mode and no new code may branch on them.
-   Physical cleanup (column, enum, partial index) is deferred to an additive migration after a production audit.

---

## 🛠 Runtime Tenant Resolution

The platform identifies the tenant for every request using a multi-step resolution strategy:

1.  **Subdomain/Domain:** (e.g., `client-a.leopardo-rh.com`).
2.  **API Header:** `X-Tenant-ID` or `X-Company-ID`.
3.  **User Context:** For authenticated requests, the tenant is derived from the user's `company_id`.

```php
// Internal tenant context — single entry point: App\Core\Tenant\TenantManager
$manager = app(TenantManager::class);

// Full tenant context (company + search_path), restored even on exception:
$manager->withinTenant($company, fn () => /* … */ null);

// Raw search_path scope (public surfaces, platform portfolio), no company
// context change, restoration guaranteed (BOS-019):
$manager->withinSearchPath('shared_tenants,public', fn () => /* … */ null);
```

---

## 🔒 Security & Data Privacy

-   **Zero-Data-Leak Policy:** Our automated tests (see `FkChainTenantIsolationTest`) verify that no query can ever bypass the tenant scope.
-   **Encryption at Rest:** Sensitive tenant data is encrypted using AES-256.
-   **Audit Logs:** Every tenant has a dedicated audit trail of all administrative actions.

---

## 🚀 Scalability

With a single shared-schema model, Leopardo scales to thousands of tenants efficiently without per-tenant DDL, while the `company_id` + global scope isolation is continuously proven by the tenant-isolation test suites (`FkChainTenantIsolationTest`, `RemainingModelsTenantIsolationTest`).

---

For technical setup, see [Deployment Guide](../deployment/DEPLOYMENT_GUIDE.md).

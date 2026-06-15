# Multi-Tenancy Strategy — Leopardo RH

Leopardo RH is built from the ground up as a native multi-tenant SaaS. Our architecture ensures that customer data remains strictly isolated, whether you are a small startup or a large enterprise with strict compliance requirements.

## 🏗 The Hybrid Isolation Model

We employ two levels of isolation based on client needs and subscription plans:

### 1. Logical Isolation (Standard)
-   **Storage:** All tenants share a common PostgreSQL schema (`shared_tenants`).
-   **Isolation Mechanism:** Every table includes a `company_id` column.
-   **Enforcement:** Global Query Scopes in Laravel automatically filter every query by the authenticated tenant's ID.
-   **Best For:** SMEs looking for a cost-effective, high-performance HR solution.

### 2. Schema-Based Isolation (Enterprise)
-   **Storage:** Each tenant has its own physical PostgreSQL schema (e.g., `tenant_abc_corp`).
-   **Isolation Mechanism:** The application dynamically switches the database `search_path` at runtime.
-   **Enforcement:** Managed by `TenantMiddleware` and `TenantManager`.
-   **Best For:** Large enterprises, government bodies, and organizations requiring strict data residency or ISO 27001 compliance.

---

## 🛠 Runtime Tenant Resolution

The platform identifies the tenant for every request using a multi-step resolution strategy:

1.  **Subdomain/Domain:** (e.g., `client-a.leopardo-rh.com`).
2.  **API Header:** `X-Tenant-ID` or `X-Company-ID`.
3.  **User Context:** For authenticated requests, the tenant is derived from the user's `company_id`.

```php
// Internal Tenant Switching Logic
public function switchToTenant(Company $company)
{
    if ($company->uses_dedicated_schema) {
        DB::statement("SET search_path TO {$company->schema_name}, public");
    } else {
        Config::set('tenant.id', $company->id);
    }
}
```

---

## 🔒 Security & Data Privacy

-   **Zero-Data-Leak Policy:** Our automated tests (see `FkChainTenantIsolationTest`) verify that no query can ever bypass the tenant scope.
-   **Encryption at Rest:** Sensitive tenant data is encrypted using AES-256.
-   **Audit Logs:** Every tenant has a dedicated audit trail of all administrative actions.

---

## 🚀 Scalability

By supporting both models, Leopardo RH can scale to thousands of small tenants efficiently while providing the heavyweight isolation required for premium enterprise clients without changing a single line of business logic.

---

For technical setup, see [Deployment Guide](../deployment/DEPLOYMENT_GUIDE.md).

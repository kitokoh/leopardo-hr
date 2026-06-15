# Performance Optimization & Strategy — Leopardo RH

High performance is a core requirement for Leopardo RH, especially for large enterprise tenants with thousands of employees.

## 🚀 Optimization Pillars

### 1. Database Performance
-   **Indexing:** All multi-tenant queries use composite indexes on `(company_id, id)` and other frequently filtered columns.
-   **Schema Isolation:** Enterprise tenants benefit from dedicated physical schemas, reducing index sizes and increasing query throughput.
-   **Eager Loading:** We strictly enforce eager loading in Eloquent to prevent N+1 query problems.

### 2. Caching Strategy (Redis)
-   **Tenant Config:** Shared tenant settings are cached for 24h.
-   **Role Matrix:** The RBAC permission matrix is cached per session.
-   **Reports:** Complex HR and attendance reports use a cached headcount and summary layer with a 5-minute TTL.

### 3. Frontend & Mobile Optimization
-   **Next.js SSR:** Server-side rendering for the dashboard ensures fast first-contentful paint.
-   **Flutter Rivderpod:** Efficient state management in the mobile apps to ensure 60fps smooth scrolling in employee lists.
-   **Image Optimization:** All profile and document assets are optimized via CDN.

---

## 📈 Benchmarks

-   **API Response Time:** < 200ms for 95% of standard requests.
-   **Payroll Calculation:** < 5s for a 250-employee company.
-   **Mobile App Load:** < 2s for initial hydration.

---

For monitoring details, see [Observability](OBSERVABILITY.md).

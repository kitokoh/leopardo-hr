# Scalability & High Availability — Leopardo RH

Leopardo RH is designed to scale with your business, from a handful of employees to enterprise-level workforce management.

## 📈 Scalability Strategy

### 1. Horizontal Scaling (Stateless API)
The backend is completely stateless. We can spin up multiple instances of the Laravel API Gateway behind a load balancer (Render/AWS) to handle increased traffic.

### 2. Database Scaling (PostgreSQL)
-   **Read Replicas:** Scale read-heavy operations (Reporting, Dashboards).
-   **Connection Pooling:** Use `pgBouncer` to manage high volumes of concurrent connections.
-   **Schema Isolation:** By isolating large enterprise clients into their own schemas, we can migrate them to dedicated database instances if they outgrow the shared infrastructure.

### 3. Background Job Scaling
Our queue system (Redis + Laravel Horizon) allows us to scale worker nodes independently. For example, during month-end payroll calculation, we can spin up 10 extra workers to process thousands of PDF payslips in seconds.

---

## ⚡ Performance Optimization

-   **Caching:** Redis-backed caching for tenant configurations, roles, and frequently accessed reports.
-   **CDN:** All static assets (icons, mobile app binaries, web frontend) are served via Cloudflare CDN.
-   **Query Optimization:** Every database query is checked for N+1 issues and indexed for optimal speed.

---

## 🔄 High Availability (HA)

-   **Zero-Downtime Deploys:** Blue/Green or Rolling deployments via Render.
-   **Multi-AZ Database:** Managed PostgreSQL with automated failover.
-   **Geographic Redundancy:** Infrastructure can be deployed across multiple regions (EU, Africa, MENA) to reduce latency and provide regional failover.

---

For monitoring details, see [Observability](OBSERVABILITY.md).

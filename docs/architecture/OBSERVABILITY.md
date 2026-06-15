# Observability & Monitoring — Leopardo RH

Maintaining 99.9% uptime and high performance requires a comprehensive observability strategy. Leopardo RH implements a three-pillar monitoring approach.

## 📊 The Three Pillars

### 1. Error Tracking (Sentry)
-   **Backend:** Real-time capture of PHP/Laravel exceptions and performance bottlenecks.
-   **Frontend:** Next.js error tracking and Core Web Vitals monitoring.
-   **Mobile:** Flutter crash reporting (Crashlytics/Sentry).

### 2. Structured Logging (JSON)
We use **Structured JSON Logging** to make logs easily searchable in cloud providers (Render, AWS CloudWatch).
-   **Audit Logs:** Track who did what and when.
-   **Performance Logs:** Monitor slow database queries and API response times.
-   **Security Logs:** Track failed login attempts and unauthorized access.

### 3. Health Monitoring
-   **Liveness Probes:** `/api/v1/health/live` ensures the service is running.
-   **Readiness Probes:** `/api/v1/health/ready` ensures the database and cache are reachable.
-   **External Probes:** Continuous monitoring from UptimeRobot and Sentry.

---

## 🛠 Metrics We Track

-   **Response Time (p95):** Target < 200ms for core API endpoints.
-   **Error Rate:** Target < 0.1% for all production requests.
-   **Tenant Health:** Real-time score based on data completeness and usage.
-   **Queue Latency:** Time taken for background jobs (like payroll PDF generation) to complete.

---

## 🚨 Alerts Configuration

Alerts are routed to:
-   **Slack:** Low-priority notifications and daily health reports.
-   **OpsGenie/PagerDuty:** Critical 5xx errors and infrastructure downtime.

---

For runbooks and incident response, see [Operations Guide](../GESTION_PROJET/RUNBOOK_OPERATIONS.md).

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

---

## 🔗 Corrélation commune (MAT-009, #5867)

Un incident doit être traçable **de l'API jusqu'au job** : chaque requête et
chaque travail asynchrone porte un identifiant de corrélation unique.

### Contrat

| Élément | Valeur |
|---|---|
| Header entrant/sortant | `X-Correlation-ID` (repli historique `X-Request-Id`) |
| Helper applicatif | `correlation_id()` (conteneur, UUID frais si absent) |
| Longueur max | 64 caractères (colonnes d'audit) |
| Propagation files | `Queue::createPayloadUsing` → `correlation_id` dans le payload de chaque job |
| Réhydratation worker | `Queue::before` (JobProcessing) → conteneur posé au démarrage du job |
| Nettoyage | `Queue::after` / `Queue::failing` → conteneur vidé après traitement |

L'identifiant est exposé dans les logs structurés (`request_id` /
`correlation_id`), les réponses API et les lignes d'audit
(`audit_logs.module_request_id`).

### Redaction PII dans les logs

Le processeur `App\Logging\PiiRedactionProcessor` est branché sur le canal
`structured` : les valeurs portées par des clés sensibles (`password`,
`token`, `secret`, `api_key`, `national_id`, `iban`, codes 2FA...) sont
remplacées par `[REDACTED]`, y compris dans les tableaux imbriqués et les
motifs `clé=valeur` des messages. Les clés techniques (`request_id`,
`duration_ms`, `method`, `uri`, `status`) restent intactes pour préserver
l'exploitabilité.

### Alertes par bounded context (convention)

Chaque BC documente dans sa fiche (registre MAT-001) au minimum :

1. **Latence** : p95 de ses endpoints critiques (cible < 200 ms).
2. **Erreurs** : taux 5xx > 0.1 % → alerte Slack/ops.
3. **Files** : profondeur, jobs failed (déjà couvert par
   `GET /api/v1/platform/observability/queues`), lag de consommation.
4. **Requêtes lentes** : `monitor:slow-queries --threshold=500` (toutes les 15 min).
5. **Sondes** : `launch-observability-smoke` (toutes les 30 min) sur API/docs/vitrine/admin.

Exemples : BC-06 Leave → alerte sur `leave:accrue` échoué ; BC-07 Payroll →
alerte sur jobs `payroll` en dead-letter ; BC-08 Accounting → alerte sur
`accounting:purge-expired-shares` échoué.

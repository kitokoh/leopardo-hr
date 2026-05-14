# 07 — MONITORING, LOGGING & OBSERVABILITE

**Objectif :** Mettre en place une observabilite complete pour la production — monitoring, logging structure, alerting, APM, error tracking.

---

## 1. Stack recommandee

| Besoin | Outil | Cout | Alternative |
|--------|-------|------|-------------|
| Error tracking | Sentry (deja installe) | Gratuit (10K events/mois) | - |
| APM (Application Performance) | Sentry Performance | Inclus | Laravel Telescope (dev only) |
| Logging structure | Render logs + Papertrail | Gratuit (Render) | Logtail/Better Stack |
| Uptime monitoring | Better Uptime / UptimeRobot | Gratuit (50 monitors) | Cronitor |
| Metrics business | Dashboard admin interne | Gratuit | - |
| Alerting | Slack/Discord webhooks | Gratuit | PagerDuty (payant) |

---

## 2. Health Check avance

### Endpoint existant

`GET /api/v1/health` — verifie DB, Redis, storage.

### Ameliorations

```php
// GET /api/v1/health — Reponse enrichie
{
    "status": "healthy",  // healthy | degraded | unhealthy
    "version": "4.1.120",
    "uptime_seconds": 86400,
    "checks": {
        "database": {"status": "up", "latency_ms": 12},
        "redis": {"status": "up", "latency_ms": 3},
        "storage": {"status": "up"},
        "queue": {"status": "up", "pending_jobs": 5},
        "traccar": {"status": "up", "latency_ms": 45},
        "ai_provider": {"status": "up", "provider": "openai"}
    },
    "stats": {
        "companies_active": 42,
        "employees_total": 1250,
        "requests_last_hour": 8400
    }
}
```

### Liveness & Readiness (Render / Kubernetes)

```
GET /api/v1/health/live     # 200 si le process tourne (pas de check DB)
GET /api/v1/health/ready    # 200 si DB + queue sont up
```

---

## 3. Logging structure

### Format de log

Tous les logs en JSON pour parsabilite :

```json
{
    "timestamp": "2026-05-10T09:15:00Z",
    "level": "info",
    "channel": "api",
    "message": "Payroll run validated",
    "context": {
        "company_id": 12,
        "user_id": 45,
        "payroll_run_id": 789,
        "employee_count": 35,
        "total_net": 1250000
    },
    "request_id": "abc-123-def",
    "ip": "192.168.1.1",
    "duration_ms": 234
}
```

### Configuration Laravel

```php
// config/logging.php — ajouter un channel JSON
'channels' => [
    'production' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'formatter' => Monolog\Formatter\JsonFormatter::class,
        'level' => 'info',
    ],
],
```

### Request ID middleware

```php
// app/Http/Middleware/RequestId.php
class RequestId {
    public function handle($request, $next) {
        $requestId = $request->header('X-Request-ID', Str::uuid()->toString());
        $request->headers->set('X-Request-ID', $requestId);
        Log::shareContext(['request_id' => $requestId]);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        return $response;
    }
}
```

---

## 4. Metriques business (Dashboard admin)

### Endpoints metriques plateforme

```
GET /api/v1/platform/metrics/overview
{
    "mrr": 2450,                    # Monthly Recurring Revenue (EUR)
    "arr": 29400,
    "active_companies": 42,
    "total_employees": 1250,
    "churn_rate_30d": 2.3,
    "new_companies_30d": 5,
    "api_requests_24h": 45000,
    "ai_requests_24h": 350,
    "ai_cost_30d_eur": 12.50,
    "avg_response_time_ms": 180,
    "error_rate_24h": 0.02,
    "uptime_30d": 99.95
}

GET /api/v1/platform/metrics/per-company
[
    {
        "company_id": 12,
        "name": "BTP Solutions",
        "plan": "business",
        "employees": 45,
        "api_requests_7d": 3200,
        "last_activity": "2026-05-10T08:00:00Z",
        "health_score": 92
    }
]
```

---

## 5. Alerting

### Alertes a configurer

| Alerte | Condition | Canal | Priorite |
|--------|-----------|-------|----------|
| API down | Health check fail > 2 min | Slack + Email | Critique |
| Erreur 500 spike | > 10 erreurs 500 en 5 min | Sentry + Slack | Haute |
| Queue bloquee | > 100 jobs pending > 15 min | Slack | Haute |
| DB slow query | Query > 2s | Log + Sentry | Moyenne |
| Certificat SSL expiry | < 14 jours | Email | Moyenne |
| Espace disque | > 85% | Slack | Haute |
| Contrat client expirant | < 7 jours | Email manager | Basse |
| Traccar deconnecte | Ping fail > 10 min | Slack | Moyenne |
| AI quota 90% | Tenant a 90% du quota IA | Notification in-app | Basse |

### Implementation Slack webhook

```php
// app/Services/AlertService.php
class AlertService {
    public function critical(string $message, array $context = []): void {
        Http::post(config('monitoring.slack_webhook'), [
            'text' => ":rotating_light: *CRITICAL* — {$message}",
            'attachments' => [['fields' => $this->formatContext($context)]]
        ]);
    }
}
```

---

## 6. Sentry — Configuration avancee

### Ce qui est deja installe

`sentry/sentry-laravel` dans composer.json.

### A configurer

```php
// config/sentry.php
'traces_sample_rate' => env('SENTRY_TRACES_RATE', 0.2),  // 20% des requetes
'profiles_sample_rate' => env('SENTRY_PROFILES_RATE', 0.1),

// Filtrer les erreurs non utiles
'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
    // Ignorer les 404 et les rate limits
    $exception = $event->getExceptions()[0] ?? null;
    if ($exception && in_array($exception->getType(), [
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
        'Illuminate\Http\Exceptions\ThrottleRequestsException',
    ])) {
        return null;
    }
    return $event;
},
```

---

## 7. Laravel Telescope (dev uniquement)

Pour le debug local, installer Telescope :

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Ne PAS activer en production (performance).

---

## 8. Slow Query Log

```php
// app/Providers/AppServiceProvider.php
if (app()->environment('production')) {
    DB::listen(function ($query) {
        if ($query->time > 1000) { // > 1 seconde
            Log::warning('Slow query', [
                'sql' => $query->sql,
                'time_ms' => $query->time,
                'bindings' => $query->bindings,
            ]);
        }
    });
}
```

---

## 9. Taches

- [x] **T-MON-01** : Enrichir `/api/v1/health` avec checks queue, traccar, AI — **FAIT** (`HealthController.php` + `tests/Feature/HealthEndpointTest.php`)
- [x] **T-MON-02** : Ajouter `/api/v1/health/live` et `/api/v1/health/ready` — **FAIT** (`tests/Feature/HealthLiveReadyTest.php`)
- [x] **T-MON-03** : Configurer logging JSON en production — **FAIT** (`StructuredLogging.php` middleware + `StructuredLoggingMiddlewareTest.php`)
- [x] **T-MON-04** : Implementer le middleware RequestId — **FAIT** (`RequestIdMiddleware.php` + `RequestIdMiddlewareTest.php`)
- [x] **T-MON-05** : Creer les endpoints metriques plateforme — **FAIT** (`MetricsController.php` + `PlatformMetricsOverviewController.php` + tests)
- [ ] **T-MON-06** : Configurer Sentry traces + profiles
- [ ] **T-MON-07** : Implementer AlertService + webhook Slack
- [ ] **T-MON-08** : Configurer slow query logging
- [ ] **T-MON-09** : Ajouter UptimeRobot/BetterUptime pour monitoring externe
- [x] **T-MON-10** : Dashboard admin avec metriques cles — **FAIT** (admin-dashboard avec DashboardController + metriques)
- [ ] **T-MON-11** : Installer Telescope pour dev
- [ ] **T-MON-12** : Documenter le runbook alertes dans `docs/GESTION_PROJET/RUNBOOK_ALERTES.md`

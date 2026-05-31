# PLAN SCALABILITÉ REDIS & ARCHITECTURE PICS DE CHARGE
## Leopardo HR — Audit Enterprise 2026

---

## 1. SIMULATION DE CHARGE

### 10 utilisateurs (Démo / Pilote)
**Profil :** 1 tenant, 10 employés actifs, usage concurrent faible.
**Infrastructure actuelle :** Suffisante. Render Free Tier + Upstash Hobby.
**Points de vigilance :** Cold start Render (~3-5s). SplashScreen implémenté (v4.16.193) — ✅ OK.
**Verdict :** ✅ FONCTIONNEL avec config Redis correcte.

### 100 utilisateurs (Bêta)
**Profil :** 5-10 tenants, 100 employés actifs, pics de pointage 8h-9h et 17h-18h.
**Goulots identifiés :**
- `GET /api/v1/employees` sans cache → N requêtes DB/minute pendant le pic matin
- `POST /api/v1/attendance/check-in` — validation + géolocalisation synchrone
- `GET /api/v1/dashboard` — agrégation multi-tables sans cache
**Actions requises :**
- Cache TenantCacheService sur les listes employees, departments, sites
- Queue pour la validation géolocalisation (pas dans le HTTP thread)
- Index DB sur `attendance_logs.employee_id + created_at`
**Verdict :** ⚠️ NÉCESSITE cache activé.

### 1 000 utilisateurs (Lancement)
**Profil :** 50-100 tenants, 1 000 employés, pics de charge significatifs.
**Goulots critiques :**
- `POST /api/v1/payroll/run` — calcul paie synchrone pour 50 employés = 10-30s de requête HTTP
- `GET /api/v1/hr-reports` — requêtes analytiques sans index ni cache
- Horizon worker avec trop peu de processus — backlog de jobs > 1000
- `GET /api/v1/attendance/monthly-report` — calcul à la demande sans cache
**Actions requises :**
- ProcessPayrollBatchJob dispatché sur queue `payroll` avec 3 workers dédiés
- Cache Redis 15min sur les rapports RH (invalidé à la création d'une nouvelle paie)
- Index composites : `(company_id, employee_id, created_at)` sur attendance_logs, absences, salary_advances
- Horizon config : `notifications:3, pdf:2, payroll:3, default:2, webhooks:1`
**Verdict :** ⚠️ NÉCESSITE queues actives + index DB.

### 10 000 utilisateurs (Scale)
**Profil :** 500+ tenants, 10 000 employés, paie mensuelle pour des milliers de personnes.
**Goulots critiques :**
- PostgreSQL single instance — saturation connexions (max 100 par défaut sur Render)
- Redis Upstash Serverless — latence si quota dépassé
- Horizon sur 1 seul processus Render — file d'attente de plusieurs heures
- `search_path` multi-schema PostgreSQL — migrations lentes sur 500+ schemas
**Actions requises :**
- PgBouncer pour pooling connexions PostgreSQL
- Read replica pour requêtes analytiques (dashboards, rapports)
- Redis Upstash Pro plan avec connexions persistantes
- K8s auto-scaling pour Horizon workers (HPAs)
- Migration vers shared_table multi-tenant si schemas trop nombreux
- CDN pour PDF et fichiers statiques (Cloudflare R2)
**Verdict :** 🔴 NÉCESSITE infrastructure dédiée.

---

## 2. STRATÉGIE REDIS UPSTASH

### Configuration recommandée pour Render + Upstash

```php
// config/database.php — Section Redis
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'), // CRITICAL: predis pour Upstash

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'leopardo_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'scheme' => 'tls', // CRITICAL pour Upstash
        'persistent' => false, // Upstash Serverless = pas de connexions persistantes
    ],

    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'scheme' => 'tls',
    ],
],
```

### Stratégie de cache par couche

| Données | TTL | Strategy | Clé |
|---|---|---|---|
| Liste employés (par tenant) | 5 min | Cache-aside | `tenant:{id}:employees` |
| Rapport présence mensuel | 15 min | Cache-aside | `tenant:{id}:report:{month}` |
| Dashboard manager | 2 min | Cache-aside | `tenant:{id}:dashboard` |
| Feature flags tenant | 10 min | Cache-aside | `tenant:{id}:features` |
| Token FCM access (Firebase) | 50 min | Cache-aside | `fcm:access_token` |
| Session utilisateur | 24h | Auto (Sanctum) | `sanctum:token:{hash}` |
| Rate limit | 1 min | Counter | `throttle:{ip}:{endpoint}` |

### Invalidation du cache

```php
// TenantCacheService — méthodes d'invalidation
public function invalidateEmployees(int $companyId): void
{
    Cache::forget("tenant:{$companyId}:employees");
}

public function invalidatePayroll(int $companyId, string $month): void  
{
    Cache::forget("tenant:{$companyId}:report:{$month}");
    Cache::forget("tenant:{$companyId}:dashboard");
}
```

---

## 3. ARCHITECTURE QUEUES — CONFIGURATION HORIZON

### Configuration Horizon recommandée

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'auto',
            'maxProcesses' => 3,
            'minProcesses' => 1,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 30,
        ],
        'supervisor-pdf' => [
            'connection' => 'redis',
            'queue' => ['pdf'],
            'balance' => 'auto',
            'maxProcesses' => 2,
            'minProcesses' => 1,
            'tries' => 3,
            'timeout' => 120, // PDF generation peut prendre 60s+
        ],
        'supervisor-payroll' => [
            'connection' => 'redis',
            'queue' => ['payroll'],
            'balance' => 'auto',
            'maxProcesses' => 3,
            'minProcesses' => 1,
            'tries' => 3,
            'timeout' => 300, // Paie batch peut prendre plusieurs minutes
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'webhooks'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

### Jobs par queue

| Queue | Jobs | SLA | Workers |
|---|---|---|---|
| `notifications` | SendPushNotificationJob, SendBulkNotificationsJob, DispatchCommunicationJob | < 5s | 3 |
| `pdf` | GeneratePaySlipPdfJob, WarmPaySlipPdfPathsForPayrollRunJob | < 120s | 2 |
| `payroll` | ProcessPayrollBatchJob, ProcessBulkPaymentJob | < 5min | 3 |
| `default` | Tâches générales | < 30s | 2 |
| `webhooks` | DispatchWebhook | < 60s | 1 |

---

## 4. INDEX DATABASE MANQUANTS

### Migrations à ajouter

```php
// Migration: add_performance_indexes
Schema::table('attendance_logs', function (Blueprint $table) {
    $table->index(['company_id', 'employee_id', 'checked_in_at']);
    $table->index(['company_id', 'status', 'checked_in_at']);
    $table->index(['employee_id', 'checked_in_at']); // pour requêtes employé
});

Schema::table('absences', function (Blueprint $table) {
    $table->index(['company_id', 'employee_id', 'start_date']);
    $table->index(['company_id', 'status', 'created_at']);
});

Schema::table('salary_advances', function (Blueprint $table) {
    $table->index(['company_id', 'employee_id', 'status']);
    $table->index(['company_id', 'created_at']);
});

Schema::table('payroll_runs', function (Blueprint $table) {
    $table->index(['company_id', 'period_start', 'status']);
});

Schema::table('notifications', function (Blueprint $table) {
    $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
});
```

---

## 5. VARIABLES D'ENVIRONNEMENT À CONFIGURER SUR RENDER

| Variable | Valeur | Description |
|---|---|---|
| `REDIS_CLIENT` | `predis` | **CRITIQUE** — Upstash nécessite predis, pas phpredis |
| `REDIS_HOST` | `REDACTED.upstash.io` | Host Upstash Redis |
| `REDIS_PORT` | `6379` | Port Redis (TLS) |
| `REDIS_PASSWORD` | `<upstash_password>` | Mot de passe Upstash (dans les credentials Upstash console) |
| `REDIS_DB` | `0` | Database par défaut |
| `REDIS_CACHE_DB` | `1` | Database dédiée au cache |
| `QUEUE_CONNECTION` | `redis` | **CRITIQUE** — Activer les queues Redis |
| `REDIS_QUEUE` | `default` | Queue par défaut |
| `REDIS_QUEUE_RETRY_AFTER` | `90` | Retry après 90 secondes |
| `CACHE_DRIVER` | `redis` | Utiliser Redis comme driver de cache |
| `SESSION_DRIVER` | `redis` | Sessions Redis (optionnel, recommandé) |
| `QUEUE_FAILED_DRIVER` | `database-uuids` | Stocker les jobs échoués en DB |
| `APP_ENV` | `production` | Environnement production |
| `APP_DEBUG` | `false` | **CRITIQUE** — Désactiver les stack traces |
| `SENTRY_DSN` | `<sentry_dsn>` | Monitoring erreurs production |
| `FIREBASE_CREDENTIALS` | `<json_base64>` | Credentials Firebase Admin SDK (base64) |
| `FIREBASE_PROJECT_ID` | `leopardo-rh` | ID projet Firebase |

### Commande de vérification post-déploiement

```bash
# Vérifier que Redis est connecté
curl https://gestionemployerbackend.onrender.com/api/v1/health/ready

# Vérifier qu'un job est bien dispatché et traité
# (log Horizon)
php artisan horizon:status
```

---

## 6. PLAN D'EXÉCUTION SCALABILITÉ

### Immédiat (1 semaine)
1. Configurer toutes les variables d'env Redis sur Render
2. Ajouter worker Horizon sur Render (Background Worker)
3. Valider le health check Redis dans `/health/ready`

### Court terme (30 jours)
4. Implémenter TenantCacheService sur employees + dashboard
5. Ajouter les index DB manquants (migration)
6. Configurer Horizon avec superviseurs par queue

### Moyen terme (60-90 jours)
7. Tests k6 baseline : 100 puis 500 utilisateurs
8. Optimiser les requêtes N+1 (Laravel Telescope / Debugbar)
9. Configurer PgBouncer si > 50 connexions simultanées

### Long terme (180 jours)
10. Read replica PostgreSQL pour requêtes analytiques
11. Auto-scaling Horizon workers (K8s HPA)
12. CDN Cloudflare R2 pour les fichiers statiques

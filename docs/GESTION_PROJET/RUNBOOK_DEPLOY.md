# RUNBOOK - DEPLOY PRODUCTION / STAGING
# Version 4.1.4 | 17 Avril 2026

## Principe
- **PR verte d'abord**
- **Merge sur `main` ensuite**
- **Deploy automatique seulement apres checks verts sur `main`**

## Preconditions
- Branch protection active sur `main`
- Auto-Deploy GitHub direct **desactive** dans Render pour eviter un deploy avant la fin des checks GitHub
- Checks GitHub obligatoires configures :
  - `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)`
  - `Backend Security (Composer Audit)`
  - `Mobile Flutter (Stable Channel)`
  - `Governance Gates (changelog + canonical files)`
  - `Dependency Review (PR Security)`
  - `CodeQL (Backend)`
- Secrets GitHub presents :
  - `RENDER_DEPLOY_HOOK_URL`
  - `FIREBASE_APP_ID`
  - `FIREBASE_TOKEN`
- `API_HEALTHCHECK_URL` optionnel mais recommande
- GitHub Security active :
  - Dependabot alerts
  - Dependabot security updates
  - Secret scanning
  - Push protection for secrets
  - Code scanning

## Flux normal
1. Ouvrir une PR vers `main`
2. Attendre les checks GitHub
3. Si tout est vert, merger la PR
4. Le push sur `main` relance `Tests - Leopardo RH`
5. Si `Tests - Leopardo RH` est vert sur `main`, `Deploy - Leopardo RH` se lance
6. GitHub :
   - declenche le deploy Render
   - attend le healthcheck API
   - construit puis distribue l'APK staging sur Firebase

## Ce qui est deploye automatiquement
- **API** : service Render
- **Front web manager** : inclus dans l'app Laravel Render
- **Mobile staging** : APK distribue sur Firebase App Distribution

## Verification post-deploy
- `GET /api/v1/health` repond `{"status":"ok", ...}`
- `/login` charge sans erreur 500
- login manager web OK
- dashboard web OK
- endpoints attendance OK
- PDF estimation OK
- build mobile visible dans Firebase App Distribution

## Cas ou le deploy ne part pas
- merge effectue alors qu'un check requis n'est pas vert
- `RENDER_DEPLOY_HOOK_URL` absent
- `FIREBASE_APP_ID` ou `FIREBASE_TOKEN` absent
- workflow `Tests - Leopardo RH` en echec sur `main`

## Rollback
- **API/Web** : rollback Render vers le dernier deploy sain
- **Mobile** : redistribuer la derniere version stable via workflow tag/manual

## Commande humaine minimale
- travail local
- push branche
- ouvrir PR
- merger uniquement quand GitHub est vert

Le reste doit etre automatique.

---

## Workers et services de fond

### Queue Workers

L'application utilise des jobs Laravel en queue pour les operations lourdes :

| Job | Queue | Description | Frequence |
|-----|-------|-------------|-----------|
| `GenerateMonthlyInvoices` | `billing` | Generation factures mensuelles | Cron mensuel |
| `CheckTrialExpiring` | `billing` | Notification expiration trial | Cron quotidien |
| `CheckOverdueInvoices` | `billing` | Relance factures impayees | Cron quotidien |
| `PayrollCalculation` | `payroll` | Calcul paie batch | On-demand |
| `GeneratePaySlipPdf` | `default` | Generation bulletins PDF | On-demand |
| `GenerateInvoicePdf` | `default` | Generation factures PDF | On-demand |
| `WebhookDispatch` | `webhooks` | Envoi webhooks aux clients | Event-driven |
| `TrackingSync` | `tracking` | Sync positions Traccar | Cron (5 min) |

### Configuration workers Render

```yaml
# render.yaml (service worker)
services:
  - type: worker
    name: leopardo-queue-default
    env: docker
    dockerCommand: php artisan queue:work --queue=default,billing,payroll,webhooks,tracking --sleep=3 --tries=3 --max-time=3600
    envVars:
      - key: APP_ENV
        value: production
      - key: QUEUE_CONNECTION
        value: redis
```

### Scheduler (cron)

Le scheduler Laravel doit tourner sur un service dedie ou via cron :

```bash
# Cron entry (ou Render Cron Job)
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Jobs schedules configures dans `app/Console/Kernel.php` :

- `billing:generate-invoices` — mensuel (1er du mois)
- `billing:check-trial-expiring` — quotidien 08h00
- `billing:check-overdue` — quotidien 09h00
- `tracking:sync` — toutes les 5 minutes

### Variables d'environnement requises

| Variable | Service | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | Worker | `redis` en production |
| `REDIS_HOST` | Worker, API | Host Redis |
| `REDIS_PASSWORD` | Worker, API | Password Redis |
| `REDIS_PORT` | Worker, API | Port Redis (default 6379) |
| `STRIPE_SECRET` | API, Worker | Cle secrete Stripe |
| `STRIPE_WEBHOOK_SECRET` | API | Secret verification webhook Stripe |
| `CHARGILY_SECRET` | API | Cle secrete Chargily (DZ) |
| `TRACCAR_URL` | Worker | URL instance Traccar |
| `TRACCAR_TOKEN` | Worker | Token API Traccar |
| `OPENAI_API_KEY` | API | Cle API OpenAI (IA) |
| `ANTHROPIC_API_KEY` | API | Cle API Anthropic (IA) |
| `SENTRY_DSN` | API, Worker | DSN Sentry pour error tracking |

### Monitoring workers

- **Health** : `php artisan queue:monitor default,billing,payroll --max=100` (alerte si > 100 jobs en attente)
- **Horizon** : envisager Laravel Horizon pour le monitoring UI des queues Redis
- **Logs** : les workers ecrivent sur le meme channel JSON structure que l'API
- **Restart** : apres chaque deploy, les workers doivent etre restartes (`php artisan queue:restart`)

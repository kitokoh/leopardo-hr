# Launch Observability Dashboard

Date : 2026-05-21

## Objectif

Donner une vue exploitable avant et pendant l'ouverture marketing publique. Ce tableau ne remplace pas Sentry/UptimeRobot/Better Stack, mais il fixe les signaux minimums a surveiller et le workflow GitHub qui les verifie toutes les 30 minutes.

## Sources

| Signal | Source canonique | Gate |
|---|---|---|
| Health API | `GET /api/v1/health` | `Launch Observability Smoke` |
| Documentation API | `GET /docs` et `/docs/openapi.yaml` | `Launch Observability Smoke` |
| Vitrine publique | URL Vercel + `/pricing` + `/demo` | `Launch Observability Smoke` |
| Admin plateforme | URL Cloudflare Pages | `Launch Observability Smoke` |
| Latence p95 read-only | `k6-load-smoke.yml` | manuel avant campagne |
| Erreurs 5xx | Sentry / logs Render / Vercel logs | alerte externe |
| Queue depth | `/api/v1/health` check `queue.size` + Render worker logs | alerte externe |
| Jobs failed | table `failed_jobs` / logs Laravel | runbook alerting |
| Leads demo/signup/newsletter/contact | logs structures `marketing.lead_captured` + webhooks CRM/email | suivi GTM quotidien |

## Seuils de lancement

| Indicateur | Vert | Orange | Rouge |
|---|---:|---:|---:|
| API health | 200 | degraded redis/storage | 503 ou timeout |
| `/docs` | 200 | > 2500 ms | non 200 |
| Vitrine home/pricing/demo | 200 | > 2500 ms | non 200 |
| Admin login | 200 | > 2500 ms | non 200 |
| p95 API core k6 | < 800 ms | 800-1500 ms | > 1500 ms |
| 5xx API | < 1% | 1-3% | > 3% |
| Queue depth | < 50 | 50-100 | > 100 pendant 15 min |
| Failed jobs | 0 | 1-3 investigues | > 3 ou recurrence |
| Leads demo non transmis CRM/email | 0 | webhook fail-soft ponctuel | recurrence > 15 min |

## Workflow operationnel

1. Avant campagne : lancer `Launch Observability Smoke` en manuel avec les trois URLs de production.
2. Avant campagne : lancer `k6 Load Smoke - Leopardo RH` avec tokens manager/employe de staging si disponibles.
3. Pendant campagne : surveiller les artifacts `launch-observability-smoke-report` et les alertes Sentry/UptimeRobot.
4. Chaque matin : verifier les logs `marketing.lead_captured`, le CRM/email et les leads sans suivi.
5. Si rouge : appliquer `RUNBOOK_MARKETING_ROLLBACK.md`.

## Commandes locales

```bash
bash dev-hub/tools/launch-observability-smoke.sh \
  https://gestionemployerbackend.onrender.com \
  https://gestionemployer-backend.vercel.app \
  https://leo-admin.pages.dev
```

## Variables utiles

| Variable | Usage |
|---|---|
| `LAUNCH_MAX_P95_MS` | seuil de latence par probe dans le smoke |
| `LAUNCH_SMOKE_RETRIES` | nombre de tentatives par probe avant echec definitif (`5` par defaut) |
| `LAUNCH_SMOKE_RETRY_DELAY_SECONDS` | pause entre deux tentatives quand une probe time out, retourne 5xx ou depasse la latence cible |
| `MARKETING_CRM_WEBHOOK_URL` | transmission CRM des leads |
| `MARKETING_EMAIL_WEBHOOK_URL` | confirmation email / notification interne |
| `MARKETING_LEAD_WEBHOOK_TOKEN` | secret Bearer pour webhooks marketing |
| `PAYROLL_QUEUE_PDF_WARMUP` | desactivation temporaire du warmup PDF en cas de congestion queue |

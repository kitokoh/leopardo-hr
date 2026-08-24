# 📡 ALERTS_CONFIGURATION — Alerting réel vs cibles (issue #5282)

**Version** : 2.0 · **Date** : 2026-08-22 · **Statut** : v1 documentée et
implémentée (2026-08-22, issue #5282). La v1.0 de ce guide (2026-08-15)
décrivait des cibles aspirationnelles (domaines `leopardo.com`, emails
`*@leopardo.com`, Slack/PagerDuty) — **non applicables** : le domaine n'est
pas possédé et la prod est 0 € (Render free + GitHub Actions publics). Ce
document remplace ces cibles par la configuration réelle.

> 💡 **Le cœur de l'alerting 2026 est GitHub Actions** (repo public = minutes
> illimitées) : les workflows de supervision passent en échec visible et
> notifient via l'UI/mobile GitHub. Slack est **opt-in** quand un webhook est
> disponible.

---

## 1. Ce qui est implémenté et actif (2026-08-22)

| # | Alerte | Canal actif | Seuils | Latence |
|---|---|---|---|---|
| A1 | **Queue dégradée / bloquée** | `.github/workflows/queue-supervision.yml` (cron 5 min) → run rouge + Slack opt-in | `pending > 50` par queue, `failed_jobs > 10`, `stale_reserved_jobs > 0` (réservé > 10 min) | **< 15 min** (DoD #5282) |
| A2 | **Surface API/web/admin down** | `.github/workflows/launch-observability-smoke.yml` (cron 30 min) | HTTP ≠ 200 ou latence > 10 s (cold start inclus) | ≤ 30 min |
| A3 | **Uptime API (externe)** | UptimeRobot / BetterStack / Hetzner — free tier (à activer : §3) | `GET https://gestionemployerbackend.onrender.com/api/v1/health/live` toutes les 5 min, 2 échecs → notif | ≤ 10 min |
| A4 | **Erreurs applicatives** | Sentry (SDK installé, DSN via `SENTRY_LARAVEL_DSN`/`SENTRY_DSN`) | Taux d'erreur > 5 % / 5 min ; spikes 5xx | temps réel |
| A5 | **Jobs en échec** | `queue:health-check` (`failed_jobs` dans le JSON) + alerte Slack opt-in | `SLACK_FAILED_JOBS_THRESHOLD` (défaut 10) | < 15 min |
| A6 | **Déploiement cassé** | `deploy-main.yml` rouge + hook rollback Render (`RENDER_ROLLBACK_HOOK_URL`) | échec de build/deploy | immédiat |

## 2. Configuration par canal

### GitHub Actions (backbone — déjà actif)
- Runs rouges visibles dans l'onglet **Actions** ; notifications GitHub
  (mobile/email) pour les échecs sur les branches que vous suivez.
- Supervision queue : `QUEUE_MAX_PENDING` / `QUEUE_MAX_FAILED` /
  `QUEUE_MAX_STALE_MINUTES` (repo `vars`, défauts 50 / 10 / 10).

### Slack (opt-in — rien ne part tant que le secret n'est pas posé)
- Secret repo : **`SLACK_MONITORING_WEBHOOK_URL`** (Incoming Webhook Slack).
  S'il est vide/absent, la supervision reste silencieuse (comportement
  historique conservé).
- Seuil failed_jobs : secret/env `SLACK_FAILED_JOBS_THRESHOLD` (défaut 10).
- Alertes envoyées par `App\Notifications\SlackAlertNotification` depuis
  `queue:health-check`.

### Sentry
- DSN : `SENTRY_LARAVEL_DSN` (fallback `SENTRY_DSN`) côté Render.
- Échantillonnage : `SENTRY_TRACES_SAMPLE_RATE` (défaut 0.2).
- Créer dans Sentry une **règle d'alerte** : `Error rate > 5% sur 5 min` →
  notif email/Slack ; spike 5xx → notif immédiate.

### Uptime externe gratuit (à activer, ~10 min)
1. Créer un compte UptimeRobot/BetterStack (free).
2. Monitor HTTP(S) → URL :
   `https://gestionemployerbackend.onrender.com/api/v1/health/live`
3. Intervalle 5 min, alerte après 2 échecs consécutifs, notif email.
4. Ajouter `https://gestionemployerbackend.onrender.com/api/v1/health/ready`
   (DB) en second monitor si souhaité.

## 3. Seuils (état réel 2026-08-22)

| Métrique | Warning | Critical | Source |
|---|---|---|---|
| Jobs pending (par queue) | 20 | 50 | `queue-supervision.yml` (`--max-pending`) |
| Jobs réservés > 10 min (worker mort) | 0 | > 0 | `queue:health-check` (`--max-stale-minutes`) |
| `failed_jobs` | 5 | 10 | `SLACK_FAILED_JOBS_THRESHOLD` |
| Erreurs applicatives | 3 % | 5 % / 5 min | Sentry (règle à créer) |
| Latence API (cold start inclus) | 5 s | 10 s | `launch-observability-smoke.yml` |

## 4. Cibles aspirationnelles (non implémentées — à réviser au passage payant)
Les alertes « produit » de la v1.0 (conversion < 5 %, taux de rebond,
formulaire, indexation SEO, pics de trafic) restent **hors périmètre 0 €** :
elles nécessitent un analytics propriétaire (GA4/Vercel Analytics) et des
canaux (Slack #analytics, PagerDuty). Les conserver en backlog, pas en prod.

## 5. Références
- Runbook : `docs/ops/INCIDENTS.md` (#5282) — playbooks + exercice consigné
- Supervision queue : `queue-supervision.yml` + `php artisan queue:health-check`
- Smoke surfaces : `launch-observability-smoke.yml`
- SLA pilotes : `docs/ops/SLA_PILOTES.md` (#5155) · URLs : `docs/ops/DEPLOYMENT_URLS.md`

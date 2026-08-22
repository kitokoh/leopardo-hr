# 🚨 INCIDENTS — Runbook opérations (issue #5282)

**Version** : 1.0 · **Date** : 2026-08-22 · **Objectif** : détecter une panne
de la queue (< 15 min — DoD #5282), trier, escalader et réparer sans
improvisation. Complète `docs/ops/SLA_PILOTES.md` (#5155) pour les bugs
pilotes et `docs/ops/DR.md` (#5283) pour la reprise après sinistre.

---

## 1. Détection — canaux actifs (dans l'ordre de fiabilité)

| Canal | Mécanisme | Détecte | Latence |
|---|---|---|---|
| **`queue-supervision.yml`** (cron 5 min, offset +2 min) | `php artisan queue:health-check` avec seuils (`--max-pending=50`, `--max-failed=10`, `--max-stale-minutes=10`) contre la prod (DB) ; run rouge si seuil dépassé | Queue bloquée, worker mort (jobs réservés > 10 min), backlog croissant, `failed_jobs` > 10, DB injoignable | **≤ 15 min** (5 min d'intervalle + 10 min de stale) |
| **`launch-observability-smoke.yml`** (cron 30 min) | Probes HTTP des 3 surfaces (API, web, admin) | Surfaces inaccessibles, latence > budget, cold start anormal | ≤ 30 min |
| **Uptime checker externe gratuit** (UptimeRobot / BetterStack / Hetzner) | `GET https://gestionemployerbackend.onrender.com/api/v1/health/live` (et `/ready`) toutes les 5 min | API down (HTTP non-200, timeout) | ≤ 5 min |
| **Sentry** | DSN `SENTRY_LARAVEL_DSN`/`SENTRY_DSN` ; `traces_sample_rate=0.2` ; alertes seuils : taux d'erreur > 5 % / 5 min, spikes 5xx | Erreurs applicatives non levées en HTTP | temps réel |
| **Pilotes** (canal humain) | Issue `PILOT_BLOCKER` + label `pilot-blocker` (#5155) | Bugs fonctionnels que la télémétrie ne voit pas | < 24 h (SLA) |

**DoD #5282 — preuve de la détection < 15 min** : le workflow de supervision
tourne toutes les 5 min ; un job resté réservé plus de 10 min (worker mort)
ou un backlog > 50 jobs rend le run rouge. Pire cas : 5 min (attente du
prochain run) + 10 min (seuil stale) = **15 min**. Exercice de validation :
§6.

---

## 2. Matrice de sévérité

| Sévérité | Définition | Exemples | Délai de réaction |
|---|---|---|---|
| **SEV1 — Critique** | Paie, pointage ou login impossibles en prod ; données perdues ou corrompues | Queue bloquée (worker mort), DB inaccessible, déploiement cassé, erreurs 5xx massives | Immédiat, hotfix < 24 h (#5155) |
| **SEV2 — Dégradé** | Fonctionnalité accessible mais cassée partiellement ; perf dégradée | Backlog intermittent, emails en retard, erreurs Sentry ciblées | Jour ouvré, fix normal P1 |
| **SEV3 — Mineur** | Aucun impact pilote mesurable | Job de supervision rouge transitoire, alerte sans suite | Semaine, analyse |

---

## 3. Rôles et canaux

- **Détection → constat** : tout agent/contributeur qui voit un run rouge de
  `queue-supervision` ou `launch-observability-smoke` ouvre une issue avec le
  template incident (`PILOT_BLOCKER` si pilote impacté), sinon une issue
  standard avec le lien du run.
- **Escalade** : SEV1 → notification immédiate (Slack `SLACK_MONITORING_WEBHOOK_URL`
  si configuré, sinon issue GitHub + mention d'un agent ops) ; SEV2/3 → issue.
- **Un seul hotfix à la fois** (règle #5155) : le premier agent qui
  self-assigne l'issue incident verrouille la fenêtre de correction.
- **Traçabilité** : chaque incident → issue GitHub fermée avec `Closes #N`
  + entrée au CHANGELOG (une ligne en tête d'`[Unreleased]`) + relecture du
  runbook si un playbook manque.

---

## 4. Playbooks

### 4.1 Queue bloquée / worker mort (SEV1)

**Symptômes** : run `queue-supervision` rouge avec `stale_reserved_jobs > 0`
ou `pending_jobs` en croissance ; les drains `queue-worker-fallback` finissent
en timeout ou échouent ; emails/PDF/paiements en retard.

1. Ouvrir le run rouge, relever le JSON : `queues`, `stale_reserved_jobs`,
   `failed_jobs`.
2. **Cause la plus probable** : le conteneur web Render a dépassé son quota
   (750 h/mois free tier) ou est endormi (> 15 min d'inactivité) — le drain
   GH Actions est la béquille prévue (#5204).
3. Vérifier l'état du drain : `Actions → Queue Worker — Fallback GH Actions` —
   s'il échoue avec une erreur DB, c'est la DB (→ 4.3) ; s'il tourne mais que
   le backlog grossit, les jobs plantent (→ 4.4).
4. **Réparation immédiate** : relancer le drain manuellement
   (`workflow_dispatch`) ; si des jobs sont réservés par un worker mort,
   les libérer :
   ```bash
   # En prod (contexte DB_SEARCH_PATH=shared_tenants,public) — ne PAS purger
   # les jobs pending, seulement les réservations orphelines :
   UPDATE jobs SET reserved_at = NULL
   WHERE reserved_at IS NOT NULL AND reserved_at < extract(epoch from now() - interval '10 minutes');
   ```
5. Vérifier `failed_jobs` : `php artisan queue:retry all` après correction de
   la cause racine (jamais avant).
6. Confirmer : 2 runs `queue-supervision` verts consécutifs (< 15 min).

### 4.2 Backlog croissant sans worker mort (SEV2)

**Symptôme** : `pending_jobs` > seuil mais `stale_reserved_jobs = 0`.

- Le drain tourne mais ne vide pas (jobs lents, `--max-time=280` atteint).
- Regarder les jobs les plus anciens : `SELECT queue, COUNT(*) FROM jobs
  WHERE reserved_at IS NULL AND available_at <= extract(epoch from now())
  GROUP BY queue ORDER BY 2 DESC;`
- Cause typique : un job qui re-tente (`--tries=3`) et échoue → il finit en
  `failed_jobs` ; sinon débit trop faible → augmenter la fenêtre du drain ou
  réveiller le worker Render.

### 4.3 DB inaccessible (SEV1)

**Symptômes** : `queue-supervision` rouge (`status: error`), smoke 30 min
rouge (API 503 — le `/health` renvoie 503 si DB down), login paie impossibles.

1. Vérifier le run `launch-observability-smoke` le plus récent (HTTP code).
2. `curl -s https://gestionemployerbackend.onrender.com/api/v1/health` → si
   `"status":"fail"`, la DB ne répond pas.
3. Cause probable : quota Render/Neon/Postgres dépassé, ou config
   `DB_*` modifiée. Vérifier la console du provider DB.
4. Réparation : provisionner/redémarrer la DB, vérifier le search_path
   (`DB_SEARCH_PATH=shared_tenants,public`), puis redéployer via
   `RENDER_DEPLOY_HOOK_URL` (workflow `deploy-main.yml`).
5. Si la DB était down plus de 15 min : les jobs `available_at` sont en
   retard — un drain normal les reprendra (ordre FIFO par `available_at`).

### 4.4 Erreurs applicatives / Sentry (SEV2 → SEV1)

**Symptômes** : alertes Sentry (taux d'erreur > 5 % / 5 min), `failed_jobs`
> 10, 5xx en pic.

1. Sentry → trier par volume ; ouvrir l'issue correspondante ou l'exception
   récurrente.
2. Si un job échoue en boucle : le mettre en `failed` est normal après
   `--tries=3` ; corriger la cause avant `queue:retry all`.
3. Pour un endpoint 5xx massif : garde `fail-closed` (#2614/#2615) + vérifier
   les secrets (Stripe/Chargily/Mailgun) côté Render.

### 4.5 Déploiement cassé (SEV1)

**Symptômes** : `deploy-main.yml` rouge, hook Render en échec, `/health`
anormal après déploiement.

1. Rollback immédiat via `RENDER_ROLLBACK_HOOK_URL` (documenté dans
   `docs/ops/DEPLOYMENT_URLS.md`).
2. Diagnostiquer sur la branche fautive (tests, migrations, secrets).
3. Redéployer après correction ; vérifier `/health` (200 + `"status":"ok"`)
   puis `launch-observability-smoke` vert.

---

## 5. Alerting — configuration réelle

| Alerte | Canal | Seuils | Config |
|---|---|---|---|
| Queue dégradée | Run rouge GH Actions + Slack opt-in | pending > 50 / failed > 10 / stale > 0 min (10 min) | `queue-supervision.yml` ; `SLACK_MONITORING_WEBHOOK_URL` (secret, vide = silencieux) |
| Surface down | Run rouge GH Actions | HTTP ≠ 200 / latence > 10 s | `launch-observability-smoke.yml` |
| Uptime API | UptimeRobot/BetterStack (gratuit) | 5 min, 2 échecs consécutifs → notif email | URL : `https://gestionemployerbackend.onrender.com/api/v1/health/live` |
| Erreurs app | Sentry | taux > 5 % / 5 min, spikes 5xx | `SENTRY_LARAVEL_DSN` (ou `SENTRY_DSN`), `SENTRY_TRACES_SAMPLE_RATE=0.2` |

Détails et marche à suivre de mise en place : `docs/ALERTS_CONFIGURATION.md`.

---

## 6. Exercice de détection — consigné

**Exercice #1 — « Queue bloquée » (dry-run réel, 2026-08-22)** — dans le cadre
de l'issue #5282 (DoD : panne détectée en < 15 min).

| Étape | Réalisation | Résultat |
|---|---|---|
| 1. Scénario | Worker « mort » simulé : jobs insérés en base avec `reserved_at` > 10 min (équivalent d'un worker tué en plein traitement) | — |
| 2. Supervision | `php artisan queue:health-check --max-pending=50 --max-failed=10 --max-stale-minutes=10` (driver `database`) | **FAILURE** — `stale_reserved_jobs: N` détecté, sortie JSON exploitable |
| 3. Test sans panne | Même commande sur une queue vide | **SUCCESS** — pas de faux positif |
| 4. Couverture automate | `queue-supervision.yml` (cron 5 min) : testé en `workflow_dispatch` | Run exécuté, log JSON visible |
| 5. Mesure du délai | Intervalle cron (5 min) + seuil stale (10 min) | **Pire cas : 15 min** ✓ DoD |
| 6. Enseignements | La détection repose sur le run GH Actions (repo public = minutes illimitées) ; Slack opt-in documenté ; aucun faux positif constaté | Acté |

**Prochain exercice suggéré** : exercice réel sur la prod (via
`workflow_dispatch` pendant une fenêtre calme) + exercice de restauration DR
(consigné dans `docs/ops/DR.md`, #5283).

---

## 7. Références

- Supervision queue : `.github/workflows/queue-supervision.yml` (#5282),
  commande `php artisan queue:health-check` (`api/app/Console/Commands/QueueHealthCheck.php`)
- Drain de secours : `.github/workflows/queue-worker-fallback.yml` (#5204/#5205)
- Smoke surfaces : `.github/workflows/launch-observability-smoke.yml` (#3968/#4720)
- Santé API : `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/HealthController.php`
- SLA pilotes : `docs/ops/SLA_PILOTES.md` (#5155) · DR : `docs/ops/DR.md` (#5283)
- Alerting : `docs/ALERTS_CONFIGURATION.md`

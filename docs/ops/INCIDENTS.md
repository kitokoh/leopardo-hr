# 🚨 INCIDENTS — Runbook opérations (issue #5282)

**Version** : 2.0 · **Date** : 2026-08-22 · **Périmètre** : prod 0 € (Render free
tier + Vercel free + GitHub Actions illimité) — voir `docs/ops/DEPLOYMENT_URLS.md`,
`docs/ALERTS_CONFIGURATION.md`, `docs/ops/SLA_PILOTES.md` (#5155).

**Objectif** : détecter une panne (< 15 min pour la queue — DoD #5282), trier,
escalader et réparer sans improvisation. Document fusionné (2026-08-22) des
travaux #5282 : runbook structurel (niveaux P0-P3, runbooks I1-I6, post-mortem)
+ supervision queue implémentée (`queue-supervision.yml`).

---

## 1. Surfaces de détection (état réel, vérifié 2026-08-22)

| Détection | Mécanisme | Fréquence | Signal |
|---|---|---|---|
| **Queue bloquée / worker mort** | `.github/workflows/queue-supervision.yml` : `php artisan queue:health-check` avec seuils (`--max-pending=50`, `--max-failed=10`, `--max-stale-minutes=10`) contre la prod (DB) | **cron 5 min** (offset +2 min du drain) | Run rouge + Slack opt-in (`SLACK_MONITORING_WEBHOOK_URL`) — **détection ≤ 15 min (DoD #5282, exercice §7)** |
| **Surfaces API/web/admin** | `launch-observability-smoke.yml` (probes HTTP, latence max 10 s, fail-closed #4720) | toutes les 30 min | Run rouge = surface KO / cold start anormal |
| **Uptime API (externe, optionnel)** | UptimeRobot/BetterStack free → `GET https://gestionemployerbackend.onrender.com/api/v1/health/live` (+ `/ready`) | 5 min, 2 échecs → notif | À activer (voir `docs/ALERTS_CONFIGURATION.md` §2) |
| **E2E prod** | `e2e-isolated.yml` / `e2e-staging.yml` (Playwright) | par PR + smoke | Scénario critique rouge en prod |
| **Erreurs applicatives** | Sentry (`sentry-laravel ^4.0`, `SENTRY_LARAVEL_DSN`) + StructuredLogging + handler jobs failed | temps réel | Pic d'erreurs / 5xx, job en `failed` |
| **Sécurité** | TruffleHog + secret-history scan, OWASP ZAP, Semgrep, CodeQL, Dependabot | par PR + cron | Scan rouge, alerte Dependabot |
| **Backup/DR** | `database-backup.yml` (daily 02:15 + drill mensuel) + `docs/ops/DR.md` (#5283) | jour / mois | Workflow rouge, drill échoué |
| **CI** | gates (coverage ≥ 65 %, PHPStan Strict, gouvernance) | par PR / merge | Run rouge bloquant |

**Canaux humains** : GitHub Issues (template `PILOT_BLOCKER` si impact pilote,
label `pilot-blocker`) + SLA pilotes (#5155, hotfix < 24 h).

---

## 2. Niveaux d'incident

| Niveau | Définition | Exemples | Cible |
|---|---|---|---|
| **P0** | Perte de données, prod inutilisable, violation sécurité | data-loss, 500 onboarding/création employé, secret exposé | < 4 h |
| **P1** | Parcours prospect/RH bloqué pour une partie | queue non drainée (worker mort), trial KO (#4948/#5162), Google OAuth KO (#5171) | < 24 h |
| **P2** | Dégradation sans blocage | 429 non localisés, latence élevée, backlog intermittent | < 1 semaine |
| **P3** | Cosmétique / dette | i18n partiel, doc périmée | backlog |

Règle de triage (alignée #5155) : **« la paie est-elle bloquée ? le pointage ?
le login ? »** — si oui en prod → P1 minimum.

---

## 3. Détection → diagnostic → résolution

1. **Constater** : run rouge (queue-supervision / smoke / E2E / ZAP / backup), alerte Sentry, issue `pilot-blocker`, ou signal pilote.
2. **Confirmer** : re-run le workflow (`workflow_dispatch`) pour exclure un flake/cold start ; vérifier l'état live (`/api/v1/health`, `/health/live`, `/health/ready`).
3. **Trier** : niveau P0-P3 (§2) + issue dédiée (1 incident = 1 issue ; le premier agent qui self-assigne verrouille la fenêtre de correction, un seul hotfix à la fois — règle #5155).
4. **Réparer** : appliquer le runbook du type (§4), puis PR avec test de non-régression + CHANGELOG + `Closes #N`.
5. **Clôturer** : post-mortem (§5) pour P0/P1, mise à jour du tracker (`docs/plan/PLAN_100PCT.md` §6 si wave impactée).

---

## 4. Runbooks par type d'incident

### I1 — API vitrine/admin KO (NXDOMAIN, 5xx, cold start)
- **Symptômes** : smoke rouge, E2E prod rouge, « site inaccessible ».
- **Causes connues** : DNS non possédé (#3452 — wontfix assumé), quota Vercel (#4868 non-bloquant), cold start Render > 10 s (veille 15 min), env manquante (ex. #5170).
- **Actions** : 1) re-run le smoke ; 2) `/health/ready` + logs Render ; 3) cold start → rien (documenté) ; env → appliquer `docs/ops/DEPLOYMENT_URLS.md` ; 5xx applicatif → Sentry pour la stack ; 4) rollback via `RENDER_ROLLBACK_HOOK_URL` si déploiement récent.
- **Escalade** : accès Render/Vercel = fondateur (tickets ops avec instructions exactes).

### I2 — Queue non drainée / worker mort (P1, DoD #5282)
- **Symptômes** : run `queue-supervision` rouge (`stale_reserved_jobs > 0` ou `pending_jobs` en croissance), trials bloqués, emails/PDF/paiements en retard.
- **Causes** : worker Render éteint (veille/quota 750 h), drain GH Actions KO, jobs qui plantent en boucle.
- **Actions** : 1) lire le JSON du run rouge (queues, stale, failed) ; 2) vérifier le dernier run `queue-worker-fallback` ; 3) re-déclencher le drain (`workflow_dispatch`) ; 4) libérer les réservations orphelines (worker mort) :
  ```bash
  UPDATE jobs SET reserved_at = NULL
  WHERE reserved_at IS NOT NULL AND reserved_at < extract(epoch from now() - interval '10 minutes');
  ```
  5) `php artisan queue:retry all` UNIQUEMENT après correction de la cause racine ; 6) confirmer : 2 runs `queue-supervision` verts consécutifs.
- **Diagnostic SQL** : `SELECT queue, COUNT(*) FROM jobs WHERE reserved_at IS NULL AND available_at <= extract(epoch from now()) GROUP BY queue ORDER BY 2 DESC;`

### I3 — Erreurs en masse (Sentry)
- **Actions** : 1) trier par fréquence + endpoints ; 2) isoler tenant/route (erreur cross-tenant ? garde #3597) ; 3) corriger + test de non-régression ; 4) si `failed_jobs` > 10 → rejouer après fix (`queue:retry`).

### I4 — Backup ou drill échoué
- **Actions** : 1) consulter le log `database-restore-drill-log` (artifact 90 j) ; 2) vérifier secrets (`DATABASE_URL`, `BACKUP_S3_BUCKET`, clés age) — absence = skip silencieux (notice) ; 3) re-run `workflow_dispatch mode=backup|drill` ; 4) documenter dans `docs/ops/DR.md` (DoD #5283).
- **Règle** : un drill échoué = incident P1 (restauration non prouvée).

### I5 — Régression CI / merge qui casse main
- **Actions** : 1) identifier le merge fautif (`git log origin/main` + checks) ; 2) reverter ou hotfix `hotfix/<issue>-<slug>` ; 3) garde anti-régression ajoutée (test + CHANGELOG).

### I6 — Sécurité (secret exposé, scan rouge)
- **Actions** : 1) révoquer/rotater immédiatement (procédure purge #1472/#1601) ; 2) purger l'historique git (force-push) ; 3) issue sécurité + PR ; 4) post-mortem.

### I7 — DB inaccessible (P0)
- **Symptômes** : `queue-supervision` rouge (`status: error`), `/health` → 503, login/paie impossibles.
- **Actions** : 1) `curl -s https://gestionemployerbackend.onrender.com/api/v1/health` → `"status":"fail"` = DB down ; 2) console du provider DB (quota ?) ; 3) redémarrer/provisionner + vérifier `DB_SEARCH_PATH=shared_tenants,public` ; 4) redéployer via `RENDER_DEPLOY_HOOK_URL` ; les jobs en retard repartent en FIFO (par `available_at`).

### I8 — Déploiement cassé (P0)
- **Actions** : 1) rollback immédiat via `RENDER_ROLLBACK_HOOK_URL` (cf. `docs/ops/DEPLOYMENT_URLS.md`) ; 2) diagnostiquer (tests, migrations, secrets) ; 3) redéployer + vérifier `/health` 200 `"status":"ok"` puis `launch-observability-smoke` vert.

---

## 5. Post-mortem (P0/P1 obligatoire)

Fichier : `docs/qa/POST_MORTEM_<date>.md` — sections : **Symptôme** → **Cause racine** → **Détection (comment on l'a vu, délai)** → **Correction** → **Anti-régression (test/garde)** → **Leçons pour les runbooks**.

---

## 6. Alerting — configuration réelle

| Alerte | Canal | Seuils | Config |
|---|---|---|---|
| Queue dégradée | Run rouge GH Actions + Slack opt-in | pending > 50 / failed > 10 / stale > 0 (10 min) | `queue-supervision.yml` ; `SLACK_MONITORING_WEBHOOK_URL` |
| Surface down | Run rouge GH Actions | HTTP ≠ 200 / latence > 10 s | `launch-observability-smoke.yml` |
| Uptime API | UptimeRobot/BetterStack (gratuit) | 5 min, 2 échecs → notif email | `https://gestionemployerbackend.onrender.com/api/v1/health/live` |
| Erreurs app | Sentry | taux > 5 % / 5 min, spikes 5xx | `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE=0.2` |

Détails : `docs/ALERTS_CONFIGURATION.md` (v2.0, config réelle).

---

## 7. Exercice de détection — consigné

**Exercice #1 — « Queue bloquée » (dry-run réel, 2026-08-22)** — DoD #5282 : panne détectée en < 15 min.

| Étape | Réalisation | Résultat |
|---|---|---|
| 1. Scénario | Worker « mort » simulé : jobs insérés avec `reserved_at` > 10 min | — |
| 2. Supervision | `php artisan queue:health-check --max-pending=50 --max-failed=10 --max-stale-minutes=10` (driver `database`) | **FAILURE** — `stale_reserved_jobs` détecté, JSON exploitable |
| 3. Contrôle | Même commande sur queue vide / job récemment réservé | **SUCCESS** — pas de faux positif |
| 4. Automate | `queue-supervision.yml` (cron 5 min) testé en `workflow_dispatch` | Run exécuté, log JSON visible |
| 5. Délai | Intervalle cron (5 min) + seuil stale (10 min) | **Pire cas : 15 min ✓ DoD** |
| 6. Enseignements | Détection portée par GH Actions (repo public = minutes illimitées) ; Slack opt-in documenté ; 0 faux positif | Acté |

**Prochain exercice** : tabletop P0 (avant premier beta) + exercice réel prod (`workflow_dispatch` en fenêtre calme) + exercice DR (consigné `docs/ops/DR.md`, #5283).

---

## 8. Gaps assumés (au 2026-08-22)

| Gap | Statut | Action |
|---|---|---|
| Alerte queue non vidée en < 15 min (DoD #5282) | ✅ **implémenté** (2026-08-22, PR #5306) | `queue-supervision.yml` + `queue:health-check` driver database |
| Uptime checker externe | 🟡 non activé (assumé 0 €) | Smoke GH Actions = détection ≤ 30 min ; activer UptimeRobot si besoin (§6) |
| Canal d'alerte humain | 🟡 GitHub Issues + labels | SLA pilotes #5155 à maintenir |
| Exercice de runbook (tabletop P0) | ❌ TODO | avant premier beta |

---

## 9. Références

- Supervision queue : `.github/workflows/queue-supervision.yml` + `php artisan queue:health-check` (`api/app/Console/Commands/QueueHealthCheck.php`) — #5282
- Drain de secours : `.github/workflows/queue-worker-fallback.yml` (#5204/#5205)
- Smoke surfaces : `.github/workflows/launch-observability-smoke.yml` (#3968/#4720)
- Santé API : `HealthController` (`/api/v1/health`, `/live`, `/ready`) — expose `failed_jobs` (#5282)
- SLA pilotes : `docs/ops/SLA_PILOTES.md` (#5155) · DR : `docs/ops/DR.md` (#5283) · Alerting : `docs/ALERTS_CONFIGURATION.md`
- Backup : `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` · Sécurité : purge #1472/#1601

*À mettre à jour à chaque incident P0/P1.*

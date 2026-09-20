# 🚨 INCIDENTS — Runbook opérations (issue #5282)

**Version** : 2.0 · **Date** : 2026-08-22 · **Périmètre** : prod 0 € (Render free
tier + Vercel free + GitHub Actions illimité) — voir `docs/ops/DEPLOYMENT_URLS.md`,
`docs/ops/ALERTS_CONFIGURATION.md`, `docs/ops/SLA_PILOTES.md` (#5155).

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
| **Uptime API (externe, optionnel)** | UptimeRobot/BetterStack free → `GET https://gestionemployerbackend.onrender.com/api/v1/health/live` (+ `/ready`) | 5 min, 2 échecs → notif | À activer (voir `docs/ops/ALERTS_CONFIGURATION.md` §2) |
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
- **Causes** : drain mono-conteneur Render éteint (veille/quota 750 h, redéploiement), jobs qui plantent en boucle.
- **Actions** : 1) lire le `checks.queue` du run rouge (pending, failed, oldest_reserved_seconds) ou `curl /api/v1/health | jq '.checks.queue'` ; 2) vérifier que le conteneur web Render draine (logs Render — le drain CI a été supprimé par #7694, un redéploiement Render relance le drain mono-conteneur) ; 3) libérer les réservations orphelines (worker mort) :
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

Détails : `docs/ops/ALERTS_CONFIGURATION.md` (v2.0, config réelle).

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

- Supervision queue : `.github/workflows/queue-supervision.yml` — sonde HTTP `GET /api/v1/health` sans credentials (#7694), seuils DoD #5282 ; `php artisan queue:health-check` reste disponible côté serveur (scheduler)
- Drain de secours CI : **supprimé** (#7694 — un CI n'est pas un worker de prod) ; drain = mono-conteneur Render, worker dédié à provisionner (#7649)
- Smoke surfaces : `.github/workflows/launch-observability-smoke.yml` (#3968/#4720)
- Santé API : `HealthController` (`/api/v1/health`, `/live`, `/ready`) — expose `failed_jobs` (#5282)
- SLA pilotes : `docs/ops/SLA_PILOTES.md` (#5155) · DR : `docs/ops/DR.md` (#5283) · Alerting : `docs/ops/ALERTS_CONFIGURATION.md`
- Backup : `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` · Sécurité : purge #1472/#1601

*À mettre à jour à chaque incident P0/P1.*

---

## 10. Incident 2026-09-08 — tier dev Render : env incomplète (DB_URL absente), deploys en échec silencieux (issues #6973/#6957/#6958/#6681)

**Symptôme** : le service Render dev (`gestionemployerbackend`, srv-d7dro8u7r5hc73a395pg) ne reçoit plus `main` — les hooks Render « réussissent » mais aucune nouvelle instance ne devient live ; l'ancien conteneur (SHA `623ed31`) continue de servir (API ok, couche web cassée). Les deploys échouent en `update_failed` après ~2 min 30, même en re-déployant le commit connu-bon.

**Cause racine (constat API, 2026-09-08 ~17:00Z)** : l'env du service ne contient plus les variables `DB_*` (ni `DB_URL` ni `REDIS_URL`) — l'ancien conteneur tourne avec l'env capturée à son déploiement ; tout NOUVEAU conteneur boote puis `/api/v1/health` renvoie 503 (check DB KO, pas de `DB_URL`) → Render rollback. Cause secondaire corrigée : `healthCheckPath` du service était VIDE (Render sondait `/` → 500 chiffrement) — corrigé via API vers `/api/v1/health`.

**Procédure de récupération (vérifiée via API Render/Neon)** :
1. Lire l'env actuelle : `GET /v1/services/srv-d7dro8u7r5hc73a395pg/env-vars` (les valeurs sont renvoyées en clair avec le token propriétaire).
2. ⚠️ **Piège** : `PUT /v1/services/{id}/env-vars` **remplace TOUTE** la liste (incident #6921 du 2026-09-06 : un PUT a vidé l'env). Toujours : GET complet → merger les ajouts → PUT de la liste complète → GET de contrôle.
3. Restaurer au minimum `DB_URL` = chaîne Neon DEV `postgresql://<user>:<password>@ep-<branch>.eu-west-2.aws.neon.tech/neondb?sslmode=require` (hôte DIRECT ; le pooler `-pooler` aborte les migrations DDL, leçons #6916/#6924 ; `docker-entrypoint.sh` dérive `DB_MIGRATE_URL` de `DB_URL`). Source : console Neon (console.neon.tech → projet dev → Connection details) ou API `https://api.neon.tech/v2/projects` (⚠️ `api.neon.tech` n'est PAS résoluble DNS depuis certains sandbox — utiliser console.neon.tech ou un autre environnement si le token napi_ répond pas).
4. Déclencher un deploy du HEAD main : `workflow_dispatch` sur `deploy-main.yml` avec `force_deploy=true` (input ajouté par #7000) — le gate vérifie désormais qu'une instance NOUVELLE (version ≠ baseline, #6984) sert le SHA attendu.
5. Vérifier : `GET https://gestionemployerbackend.onrender.com/api/v1/health` → `version` = SHA court du HEAD main ; `/api/v1/demo-users` → 200 ; couche web → `checks.web.ok=true`.

**Statut** : en attente de la valeur `DB_URL` (action fondateur/agent avec accès Neon fonctionnel). Le redéploiement automatique (catchup horaire `deploy-main-catchup.yml`) fera le reste dès l'env restaurée.

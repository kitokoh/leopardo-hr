# RUNBOOK INCIDENTS — Leopardo RH

**Issue** : #5282 (Programme 100 %, wave W6 — observability)
**Version** : 1.0 · **Date** : 2026-08-22
**Périmètre** : prod 0 € (Render free tier + Vercel free + GitHub Actions illimité) — voir `docs/DEPLOYMENT_PRODUCTION.md`, `docs/OPS/RENDER_QUEUE_WORKERS.md`, `docs/ALERTS_CONFIGURATION.md`.

---

## 0. Surfaces de détection (ce qui existe, vérifié 2026-08-22)

| Détection | Mécanisme | Fréquence | Qu'est-ce qu'un signal ? |
|---|---|---|---|
| **Uptime HTTP** | Workflow `launch-observability-smoke.yml` (probes API/web/admin, latence max, fail-closed #4720) | toutes les 30 min | Run rouge = surface KO ou > 10 s (cold start) |
| **E2E prod** | `e2e-isolated.yml` / `e2e-staging.yml` (Playwright) | par PR + smoke | Scénario critique rouge en prod |
| **Erreurs applicatives** | Sentry (`sentry-laravel ^4.0`) + `StructuredLogging` + handler jobs failed (#4399) | temps réel | Pic d'erreurs, job en `failed` |
| **Queue** | Drain GH Actions (`queue-worker-fallback.yml`, cron */5) + `docs/OPS/RENDER_QUEUE_WORKERS.md` | 5 min | `jobs` non vidée, trials `pending` > 15 min (#4948) |
| **Sécurité** | TruffleHog + secret-history scan, OWASP ZAP baseline, Semgrep, CodeQL, Dependabot | par PR + cron | Scan rouge, alerte Dependabot 0 toléré |
| **Backup** | `database-backup.yml` (daily 02:15 + drill mensuel) | jour / mois | Workflow rouge, drill échoué (voir `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md`) |
| **CI** | 43 workflows, gates (coverage ≥ 80 % payroll, PHPStan Strict, gardes) | par PR / merge | Run rouge bloquant, gate franchie |

**Absences assumées** : pas d'uptime checker externe payant, pas de PagerDuty → la détection repose sur GitHub Actions + Sentry (gratuit). Les canaux d'alerte humains sont GitHub Issues (template `PILOT_BLOCKER` si impact pilote, label `pilot-blocker`) et le SLA pilotes (#5155, hotfix < 24 h).

---

## 1. Niveaux d'incident

| Niveau | Définition | Exemples | Cible de résolution |
|---|---|---|---|
| **P0** | Perte de données, prod inutilisable, violation sécurité | data-loss, 500 sur onboarding/création employé, secret exposé | < 4 h |
| **P1** | Parcours prospect/RH bloqué pour une partie | trial KO (#4948/#5162), queue non drainée, Google OAuth KO (#5171) | < 24 h |
| **P2** | Dégradation sans blocage | 429 non localisés, latence élevée, label obsolète | < 1 semaine |
| **P3** | Cosmétique / dette | i18n partiel, doc périmée | backlog |

---

## 2. Détection → diagnostic → résolution (séquence type)

1. **Constater** : run rouge sur GitHub Actions (Smoke/E2E/ZAP/backup), alerte Sentry, issue `pilot-blocker`, ou signal client.
2. **Confirmer** : re-run le workflow (workflow_dispatch) pour exclure un flake/cold start ; vérifier l'état live (`/health`, `/health/live`, `/health/ready` — v4.24.0).
3. **Trier** : niveau P0-P3 (table §1) + assignation issue dédiée (1 incident = 1 issue, `Closes` sur la PR de fix).
4. **Réparer** : appliquer le runbook du type (§3), puis PR avec test de non-régression + CHANGELOG + `Closes #N`.
5. **Clôturer** : post-mortem (§4) pour P0/P1, mise à jour du tracker (§6 du PLAN_100PCT si wave impactée).

---

## 3. Runbooks par type d'incident

### I1 — API vitrine/admin KO (NXDOMAIN, 5xx, cold start)
- **Symptômes** : smoke rouge, E2E prod rouge, clients « site inaccessible ».
- **Causes connues** : DNS non possédé (#3452 — wontfix assumé), quota Vercel épuisé (#4868 non-bloquant), cold start Render > 10 s (free tier 15 min de veille), env manquante (ex. #5170 Google OAuth).
- **Actions** : 1) re-run le smoke ; 2) vérifier `/health/ready` + logs Render (web worker) ; 3) si cold start → rien (documenté) ; si env → appliquer `docs/OPS/GOOGLE_OAUTH_ENV.md` / `docs/OPS/RENDER_QUEUE_WORKERS.md` §env ; 4) si 5xx applicatif → Sentry pour la stack.
- **Escalade** : accès Render/Vercel = fondateur (tickets ops avec instructions exactes).

### I2 — Queue non drainée (trials `pending`, invitations jamais envoyées)
- **Symptômes** : trials bloqués en `pending` > 15 min, `invitations` jamais traitées, `jobs` DB qui grossit (historique #4948/#5172).
- **Causes** : worker Render éteint (veille), drain GH Actions KO, Redis Upstash quota épuisé (drivers redis→file #5206/#5207).
- **Actions** : 1) `SELECT queue, COUNT(*) FROM jobs GROUP BY queue` (via console Render/psql) ; 2) vérifier le dernier run `queue-worker-fallback` (vert ?) ; 3) re-déclencher le drain (`workflow_dispatch`) ; 4) si drivers redis : `php artisan infra:probe-availability` → fallback file automatique ; 5) drain manuel des `trial_provisionings` pending périmés (procédure `docs/OPS/RENDER_QUEUE_WORKERS.md`).
- **SLA** : alerte si file non vidée en < 15 min (DoD #5282) — cible à couvrir par une supervision dédiée (voir « Suivi » §5).

### I3 — Erreurs en masse (Sentry)
- **Actions** : 1) trier par fréquence + endpoints ; 2) isoler tenant/route (erreur cross-tenant ? garde #3597) ; 3) corriger + test de non-régression ; 4) si `failed` jobs : rejouer après fix (`queue:retry`).

### I4 — Backup ou drill échoué
- **Actions** : 1) consulter le log `database-restore-drill-log` (artifact 90 j) ; 2) vérifier secrets (`DATABASE_URL`, `BACKUP_S3_BUCKET`, clés age) — absence = skip silencieux (notice) ; 3) re-run `workflow_dispatch mode=backup|drill` ; 4) documenter dans `RUNBOOK_DRILLS_LOG.md` (DoD #5283).
- **Règle** : un drill échoué = incident P1 (restauration non prouvée).

### I5 — Régression CI / merge qui casse main
- **Actions** : 1) identifier le merge fautif (`git log origin/main` + checks) ; 2) reverter ou hotfix `hotfix/<issue>-<slug>` (CI minimale paie + E2E) ; 3) garde anti-régression ajoutée (test + CHANGELOG).

### I6 — Sécurité (secret exposé, scan rouge)
- **Actions** : 1) révoquer/rotater immédiatement (procédure purge #1472/#1601 — `docs/security/POST_MORTEM_PURGE_2026-08-11.md`) ; 2) purger l'historique git (force-push) ; 3) issue sécurité + PR ; 4) post-mortem.

---

## 4. Post-mortem (P0/P1 obligatoire)

Fichier : `docs/qa/POST_MORTEM_<date>.md` — sections : **Symptôme** → **Cause racine** → **Détection (comment on l'a vu, délai)** → **Correction** → **Anti-régression (test/garde)** → **Leçons pour les runbooks**.

---

## 5. Suivi (gaps assumés au 2026-08-22)

| Gap | Statut | Action |
|---|---|---|
| Alerte queue non vidée en < 15 min (DoD #5282) | ❌ absent | Supervision dédiée (workflow/cron + check `jobs`) — à implémenter |
| Uptime checker externe | ❌ absent (assumé 0 €) | Smoke GH Actions = détection ≤ 30 min |
| Canal d'alerte humain | 🟡 GitHub Issues + labels | SLA pilotes #5155 à maintenir |
| Exercice de runbook (tabletop P0) | ❌ TODO | DR-03 du `RUNBOOK_DRILLS_LOG.md` (avant premier beta) |

---

*Runbook incidents — issue #5282. Sources : workflows `.github/workflows/` (smoke, queue, backup, sécurité), `docs/ALERTS_CONFIGURATION.md`, `docs/OPS/*`, `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`. À mettre à jour à chaque incident P0/P1.*

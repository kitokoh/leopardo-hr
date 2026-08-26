# RUNBOOK — Workers de queue & scheduler sur Render (issue #5172)

**Version** : 1.0 · **Date** : 2026-08-20 · **Auteur** : Agent PM (batch #5172)
**Symptôme traité** : invitations / trial provisioning / notifications jamais traités — prospects bloqués en « pending ».

---

## 1. Constat (vérifié via API Render, 2026-08-20)

Le compte Render `africanovatech` ne contient **qu'un seul service** : le web service `gestionemployerbackend`.
Les workers définis dans `render.yaml` sont **absents** :

- `leopardo-queue-worker` (queue:work) — **n'existe pas**
- `leopardo-scheduler` (schedule:run) — **n'existe pas**

En complément, l'env live dévie de `render.yaml` :
`QUEUE_CONNECTION=database` (source de vérité unique #5578, alignée render.yaml), `SESSION_DRIVER=file`, `CACHE_STORE=file`.

## 2. Impact

- `ProvisionDemoTenantJob` (trial guidé) + tous les jobs de la file `default`
  (emails d'invitation, notifications, PDF, payroll) **ne sont jamais exécutés**.
- Conséquence directe sur l'onboarding Google : même avec des credentials OAuth
  valides, l'email d'invitation ne part jamais (issue #5170/#5171 bloquées en aval).
- Rejoint #4948 (trial bloqué « pending ») et #5162 (trial OTP → 503).

## 3. Provisionner les deux workers (action ops — accès Render requis)

### Option A — Blueprint (recommandée, fidèle à render.yaml)

1. Dashboard Render → **New + → Blueprint** → sélectionner le repo GitHub
   `kitokoh/leopardo-hr` → **Apply**.
2. Render détecte les 3 services + 2 bases de `render.yaml` et les provisionne.
3. ⚠️ Les variables marquées `sync: false` (secrets : `GOOGLE_*`, `MAIL_PASSWORD`,
   `STRIPE_*`, `SUPER_ADMIN_PASSWORD`…) ne seront **pas** remplies par le blueprint
   → les renseigner à la main après l'apply (voir §4).
4. Vérifier le plan gratuit Redis : `leopardo-redis` doit exister (instance interne,
   `maxmemoryPolicy: allkeys-lru`).

> Si l'option A écrase l'env existante du web service (vars `sync: true`), re-saisir
> les secrets `sync: false` indiqués en §4 AVANT de redéployer.

### Option B — Services individuels (dashboard)

Pour chaque worker manquant, reproduire la définition `render.yaml` :

| Service | Type | Dockerfile | DockerCommand |
|---|---|---|---|
| `leopardo-queue-worker` | Background Worker | `api/Dockerfile.prod` | `php artisan queue:work --queue=webhooks,audit,notifications,emails,pdf,payroll,documents,default --tries=3 --timeout=300 --sleep=3 --max-jobs=500 --max-time=3600` (#5578 : connexion par défaut = database) |
| `leopardo-scheduler` | Background Worker | `api/Dockerfile.prod` | `sh -c "while true; do php artisan schedule:run --no-interaction; sleep 60; done"` |

Copier le bloc envVars du web service (DB, REDIS, APP_KEY via `fromService`, MAIL_*).
**Critique** : `APP_KEY` doit être **identique** au web service (`fromService` /
`envVarKey`) — sinon sessions/fichiers chiffrés illisibles entre services (#3907).

### Option C — API Render (scriptable)

```bash
RENDER_API_KEY=<clé> dev-hub/tools/render-verify-services.sh   # état actuel
# Puis POST https://api.render.com/v1/services avec le payload du worker
# (modèle : la section `- type: worker` de render.yaml convertie en JSON,
#  ownerId = ID de l'équipe africanovatech).
```

## 4. Aligner l'env du web service (QUEUE_CONNECTION/CACHE_STORE/SESSION_DRIVER)

Dashboard Render → service `gestionemployerbackend` → **Environment** :

| Variable | Valeur | Note |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | #5578 : source de vérité unique (plus de dérive) |
| `CACHE_STORE` | `redis` | (constat : `file` en live) |
| `SESSION_DRIVER` | `redis` | (constat : `file` en live) |
| `REDIS_URL` / `REDIS_PASSWORD` | instance `leopardo-redis` | `fromDatabase` dans render.yaml |

Ces trois variables sont déclarées `sync: true` dans render.yaml — un apply blueprint
les corrigera automatiquement ; les garder `sync: false` ne concerne que les secrets.

## 5. Drainer les trial_provisionings bloqués (base de données)

Deux outils existent déjà (créés pour #4948) :

```bash
# 1. Inventaire (dry-run, sans écriture)
php artisan trial-provisionings:sweep --dry-run

# 2. Marquage failed des lignes bloquées > 30 min (fail-loud)
php artisan trial-provisionings:sweep

# 3. Re-dispatch auto des lignes reconstructibles (disponible si programmé)
php artisan trial:provisioning-sweep
```

Alternativement, un SQL direct (PostgreSQL, schéma public) :

```sql
-- Inventaire
SELECT id, email, status, created_at, updated_at
FROM public.trial_provisionings
WHERE status IN ('pending', 'provisioning_sandbox')
ORDER BY created_at;

-- Marquage failed des lignes orphelines (à exécuter seulement après vérification)
UPDATE public.trial_provisionings
SET status = 'failed',
    error = 'OPS_DRAIN: worker absent en prod avant #5172 — re-signup requis',
    updated_at = NOW()
WHERE status IN ('pending', 'provisioning_sandbox')
  AND updated_at < NOW() - INTERVAL '30 minutes';
```

⚠️ Ne **jamais** supprimer les lignes (traçabilité QA) ; `failed` est l'état terminal
propre — l'anti-doublon #3951 ne réutilise que les lignes `pending`, donc un re-signup
repartira proprement.

## 6. Vérifier la délivrabilité des emails de bout en bout

```bash
# Worker vivant ? (logs Render du leopardo-queue-worker)
#   « processing: App\Jobs\... » / « processed successfully »
# Redis consommé ? (metrics Redis : connected_clients, keyspace_hits)
# Envoi réel : créer un test d'invitation en staging et vérifier Mailgun
# (Events log : accepted → delivered).
```

Le transport Mail est l'API HTTP Mailgun (`MAIL_MAILER=mailgun`, #5139) — le SMTP
sortant est bloqué par Render. Aligner `MAIL_MAILER`/`MAILGUN_*` sur les workers
(le web service a déjà le bloc dans render.yaml).

## 7. Gardes anti-régression

- Script de vérification : `dev-hub/tools/render-verify-services.sh` (exit 1 si un
  service attendu manque ou si l'env dévie) — à brancher sur un cron/heartbeat ops.
- Sweeper déjà en place : `SweepStaleTrialProvisioningsCommand` (fail-loud si un
  provisioning reste bloqué > 30 min).
- Télémétrie conseillée : alerte si un job trial reste `pending` > 15 min (prévention
  récidive #4948/#5172).

---

*Runbook issu du constat QA 2026-08-20 (issues #5172/#4948/#5162). Voir aussi
`docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` et `docs/OPS/BUDGET_AGENTS.md`.*

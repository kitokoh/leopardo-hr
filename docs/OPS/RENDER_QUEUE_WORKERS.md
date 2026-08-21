# ⚙️ Runbook — Workers de queue & scheduler sur Render (issue #5172)

**Version** : 1.0 · **Date** : 2026-08-20 · **Statut** : à exécuter par un humain (ops / fondateur)
**Portée** : compte Render `africanovatech`, service web `gestionemployerbackend` (prod)

> ⚠️ **Action humaine requise** : le provisioning des services Render se fait depuis le
> dashboard Render. Ce runbook donne les étapes, les commandes de vérification et le
> runback. Rien ici ne modifie `render.yaml` (déjà correct — il déclare les 2 workers
> + Redis).

---

## 1. Contexte & constat

- **`render.yaml` est déjà correct** : il déclare 3 services — le web
  `gestionemployerbackend`, le worker `leopardo-queue-worker`
  (`php artisan queue:work redis --queue=webhooks,audit,notifications,emails,pdf,payroll,documents,default …`),
  le worker `leopardo-scheduler` (`schedule:run` toutes les minutes) — plus les
  instances `leopardo-db` (PostgreSQL) et `leopardo-redis` (Redis interne, #3774).
- **Constat prod (2026-08-20)** : le compte Render `africanovatech` ne contient
  **qu'un seul service** (le web `gestionemployerbackend`). Les 2 workers n'existent
  **pas** → aucun job n'est consommé : `ProvisionDemoTenantJob` (trial guided),
  emails d'invitation, notifications, PDF, payroll restent en file → les prospects
  restent bloqués en `pending` / `provisioning_sandbox` indéfiniment.
- **Dérive d'env du web service** : `QUEUE_CONNECTION=database`,
  `SESSION_DRIVER=file`, `CACHE_STORE=file` (render.yaml prévoit `redis` pour les
  trois). Une file `database` n'est consommée par aucun worker et laisse les jobs
  s'accumuler dans la table `jobs`.
- **Symptômes associés** : #4948 (pending silencieux — le prospect poll
  `GET /trial/status` sans jamais voir `ready`/`failed`), #5162 (trial self-service →
  `503 TRIAL_OTP_SEND_FAILED`), QA prod 2026-08-19 (`docs/qa/QA_PROD_2026-08-19.md`).
  Référence QA onboarding Google (si présent) : F3/F7 du rapport
  `tasks/leopardo-qa-onboarding-google-2026-08-20/RAPPORT_PM_2026-08-20.md`.

> ℹ️ **Vérifier l'état réel avant de conclure** : les symptômes peuvent varier (un
> worker temporaire/manuel a pu traiter ponctuellement des jobs, cf. QA 2026-08-19 où
> le job s'exécutait). **Source de vérité = dashboard Render** → liste des services du
> compte `africanovatech`.

### Services attendus après exécution

| Service | Type | Commande | Plan |
|---|---|---|---|
| `gestionemployerbackend` | web | serveur HTTP (Dockerfile.prod) | starter |
| `leopardo-queue-worker` | worker | `queue:work redis` (7 queues + default) | starter |
| `leopardo-scheduler` | worker | `schedule:run` toutes les 60 s | starter |
| `leopardo-db` | database | PostgreSQL | starter |
| `leopardo-redis` | database (redis) | cache/queue/session | free |

---

## 2. Étape 0 — Prérequis & constat initial

1. **Accès dashboard Render** (compte `africanovatech`, rôle owner).
2. **Accès DB prod** pour les requêtes SQL : shell Render du web service
   (`php artisan tinker` / `psql` via la variable `DATABASE_URL`) **ou** `psql` local
   avec les credentials `DB_*` du dashboard.
3. **Accès shell d'un service Render** pour les commandes `php artisan` (onglet
   **Shell** du service ; l'env du service est injecté automatiquement).

Constat initial (à noter avant toute modification) :

```bash
# 1. Liste des services du compte (dashboard Render → onglet « Services ») :
#    → attendu AVANT : uniquement gestionemployerbackend (+ DB/Redis dans « Databases »).

# 2. Santé API + driver de queue effectif :
curl -s https://gestionemployerbackend.onrender.com/api/v1/health | jq '{status, checks: {queue: .checks.queue, redis: .checks.redis}}'
#    → queue.driver doit répondre « redis » APRÈS l'étape 2 (avant : « database »).

# 3. Volume de jobs en attente (file database résiduelle) :
psql "$DATABASE_URL" -c "SELECT queue, count(*) FROM jobs GROUP BY queue ORDER BY 2 DESC;"
psql "$DATABASE_URL" -c "SELECT count(*) AS failed_jobs FROM failed_jobs;"

# 4. Trial provisionings bloqués :
psql "$DATABASE_URL" -c "SELECT status, count(*) FROM public.trial_provisionings GROUP BY status ORDER BY 2 DESC;"
psql "$DATABASE_URL" -c "SELECT id, email, status, updated_at FROM public.trial_provisionings WHERE status IN ('pending','provisioning_sandbox') ORDER BY updated_at;"
```

---

## 3. Étape 1 — Provisionner les 2 workers depuis le blueprint render.yaml

> `render.yaml` déclare déjà `leopardo-queue-worker` et `leopardo-scheduler`.
> Deux chemins possibles (A = recommandé : blueprint, B = création manuelle).

### 3.A — Via le Blueprint (recommandé)

1. Dashboard Render → **Blueprints** → instance liée au repo `kitokoh/leopardo-hr`.
2. **Sync** / **« Apply blueprint »** : Render détecte les changements de
   `render.yaml` (2 nouveaux services worker).
3. **Relire le diff proposé avant d'appliquer** :
   - ✅ attendu : création de `leopardo-queue-worker` + `leopardo-scheduler`
     (plan `starter`, région `frankfurt`, dockerfile `api/Dockerfile.prod`) ;
   - ⚠️ Render peut aussi proposer de resynchroniser l'env du web service
     (QUEUE_CONNECTION/CACHE_STORE/SESSION_DRIVER → `redis`) — c'est **désiré**
     (cf. étape 2), mais vérifiez qu'il ne touche **pas** aux valeurs `sync: false`
     (secrets : MAIL_PASSWORD, STRIPE_*, GOOGLE_*, FIREBASE_*, SUPER_ADMIN_PASSWORD…) ;
   - ⛔ ne **jamais** recréer/supprimer `leopardo-db` ni `leopardo-redis` (perte de
     données). Si le blueprint les liste comme « existants », ne rien changer.
4. **Appliquer** puis attendre la fin des builds (image `api/Dockerfile.prod` ×2).
5. Vérifier que chaque worker passe **Live** et que ses logs démarrent proprement
   (voir 3.C).

### 3.B — Création manuelle (si le blueprint ne propose pas les services)

1. **New +** → **Background Worker** → connecter le repo `kitokoh/leopardo-hr` /
   branche `main`.
2. Paramètres **identiques à render.yaml** (copier-coller, ne pas inventer) :
   - `Dockerfile Path` : `api/Dockerfile.prod` · `Root Directory` : `.` ·
     `Region` : `frankfurt` · `Instance Type` : **Starter** (pas free tier : les
     workers gratuits sont suspendus après 15 min d'inactivité → jobs en attente).
   - `Start Command` (worker queue) :
     ```
     php artisan queue:work redis --queue=webhooks,audit,notifications,emails,pdf,payroll,documents,default --tries=3 --timeout=300 --sleep=3 --max-jobs=500 --max-time=3600
     ```
   - `Start Command` (scheduler) :
     ```
     sh -c "while true; do php artisan schedule:run --no-interaction; sleep 60; done"
     ```
3. **Variables d'env** : reprendre le bloc `envVars` du service correspondant dans
   `render.yaml` (DB_*, REDIS_*, APP_KEY référencé **depuis le web service** — #3907,
   ne pas générer de nouvelle clé, MAIL_*, FIREBASE_*, STRIPE_*, etc.).
4. Créer les deux services puis vérifier les logs (3.C).

### 3.C — Vérification immédiate

```bash
# Depuis le shell du worker queue : le process tourne et consomme
php artisan queue:monitor redis
#   → tableau des queues (webhooks, audit, notifications, emails, pdf, payroll,
#      documents, default) — les tailles doivent décroître après l'étape 3 (drain).

# Depuis le shell du scheduler : les tâches planifiées sont bien enregistrées
php artisan schedule:list
#   → doit lister trial-provisionings:sweep (toutes les 15 min, #4948),
#     billing:check-trials (daily), edge:monitor, etc.

# Logs du worker (dashboard → service → Logs) : aucune trace de
# « Failed to provision sandbox » ni de stack traces ; les jobs consommés
# apparaissent (Log::info ProvisionDemoTenantJob started / Sandbox provisioned).
```

---

## 4. Étape 2 — Repasser QUEUE/SESSION/CACHE sur Redis (web service)

L'env live dévie : `QUEUE_CONNECTION=database`, `SESSION_DRIVER=file`,
`CACHE_STORE=file` (render.yaml → `redis`). Tant que `QUEUE_CONNECTION=database`, les
nouveaux jobs partent dans la table `jobs` que **personne** ne consomme.

1. Dashboard → **gestionemployerbackend** → **Environment**.
2. Modifier les 3 variables :
   - `QUEUE_CONNECTION` : `database` → **`redis`**
   - `SESSION_DRIVER` : `file` → **`redis`**
   - `CACHE_STORE` : `file` → **`redis`**
   - (vérifier au passage `REDIS_CLIENT=predis` et `REDIS_URL` / `REDIS_PASSWORD`
     pointant vers l'instance interne `leopardo-redis`, #3774).
3. **Save & Deploy**.
4. Si une variable a été éditée manuellement, Render la considère comme « override
   manuel » : la remettre à la valeur du blueprint (ou la supprimer pour qu'elle
   redevienne pilotée par render.yaml). Le script de parité
   `dev-hub/tools/check-render-env-parity.sh` vérifie l'alignement.

> ℹ️ **Ordre conseillé** : workers (étape 1) **puis** env web (étape 2) **puis**
> drain (étape 3). Basculer l'env web avant les workers est sans danger (les jobs
> redis attendront simplement le worker), mais ne laissez pas `database` en place.

### Vérification

```bash
# Driver de queue effectif (doit répondre redis) :
curl -s https://gestionemployerbackend.onrender.com/api/v1/health | jq '.checks.queue'
# Redéploiement propre : /api/v1/health → status ok, version attendue.

# Parité env render.yaml ↔ Render (outillage #5172) :
# 1) Exporter l'env de chaque service (dashboard → service → Environment → Copy/Export)
#    vers des fichiers render-web.env.txt / render-worker.env.txt / render-scheduler.env.txt
#    (ne jamais committer ces fichiers — *.env.* est gitignoré).
# 2) Lancer le check :
dev-hub/tools/check-render-env-parity.sh --live
#   → « ✓ Parité env render.yaml ↔ Render » ; exit 0.
```

---

## 5. Étape 3 — Drainer les `trial_provisionings` bloqués en `pending`

### Rappel du schéma (source de vérité = migrations)

`public.trial_provisionings` (`2026_08_15_000001` + `2026_08_15_000003` +
`2026_08_15_000012` + `2026_08_18_000001`) :

| Colonne | Type | Rôle |
|---|---|---|
| `id` | bigint PK | — |
| `email` | string(255), index | email prospect |
| `provisioning_token` | string(64), unique | poll `GET /trial/status` |
| `company_name` / `country` | string(120) / string(2), nullable | **arguments du job** (re-dispatch possible depuis la migration 2026_08_18_000001) |
| `attempts` | smallint unsigned, défaut 0 | bornes du re-dispatch (#4948) |
| `status` | string(20), défaut `pending` | `pending` \| `ready` \| `failed` |
| `company_id` / `login_url` | uuid(50) / string(500), nullable | résultat du provisioning |
| `error` | string(500), nullable | message d'échec |
| `access_sent_at` / `provisioned_at` | timestamps | horodatages |
| index unique partiel | `(email) WHERE status='pending'` | anti-doublon #3951 |

`ProvisionDemoTenantJob` : `tries=5`, backoff 30/60/120/300 s (≈ 8,5 min max).
→ **une ligne `pending` de plus de ~30 min est définitivement orpheline** (worker
jamais exécuté).

### 5.A — Inventaire

```bash
# Comptage par statut :
psql "$DATABASE_URL" -c "SELECT status, count(*) FROM public.trial_provisionings GROUP BY status ORDER BY 2 DESC;"

# Lignes bloquées (> 30 min) — celles à traiter :
psql "$DATABASE_URL" -c "SELECT id, email, status, company_name, country, attempts, updated_at
  FROM public.trial_provisionings
  WHERE status IN ('pending','provisioning_sandbox') AND updated_at < now() - interval '30 minutes'
  ORDER BY updated_at;"

# Outillage #5172 (dry-run par défaut — aucune écriture) :
php dev-hub/tools/drain-pending-trial-provisionings.php --action=list --max-age-minutes=30
```

### 5.B — Re-queuer proprement (recommandé si les workers sont opérationnels)

Le re-dispatch du vrai job (`ProvisionDemoTenantJob` avec email, company_name,
country, token) est le plus propre : le job repart avec ses retries, et son hook
`failed()` passera la ligne en `failed` si ça échoue encore.

```bash
# Auto (déjà présent dans l'app, non planifié — à lancer une fois les workers up) :
php artisan trial:provisioning-sweep
#   → re-dispatche les pending > 30 min (max 3 tentatives, colonne attempts),
#     passe en failed les lignes non reconstructibles (sans company_name/country).

# Outillage #5172 — même chose, piloté manuellement (dry-run d'abord) :
php dev-hub/tools/drain-pending-trial-provisionings.php --action=requeue --max-age-minutes=30
php dev-hub/tools/drain-pending-trial-provisionings.php --action=requeue --max-age-minutes=30 --apply
```

> ⚠️ **Ne re-queuer QUE les lignes reconstructibles** (`company_name` + `country`
> renseignés) — le job les exige. Les lignes antérieures à la migration
> `2026_08_18_000001` sont à passer en `failed` (le sweeper le fait automatiquement).

### 5.C — Marquer `failed` (si on ne veut pas relancer)

```bash
# Commande existante (planifiée toutes les 15 min, #4948) :
php artisan trial-provisionings:sweep --dry-run --max-age-minutes=30   # prévisualisation
php artisan trial-provisionings:sweep --max-age-minutes=30             # application

# Outillage #5172 :
php dev-hub/tools/drain-pending-trial-provisionings.php --action=fail --max-age-minutes=30
php dev-hub/tools/drain-pending-trial-provisionings.php --action=fail --max-age-minutes=30 --apply
```

### 5.D — Vérification du drain

```bash
# Plus aucune ligne pending orpheline (le job normal passe à ready/failed en < 10 min) :
psql "$DATABASE_URL" -c "SELECT count(*) AS stale_pending FROM public.trial_provisionings
  WHERE status='pending' AND updated_at < now() - interval '30 minutes';"
# → 0 attendu.

# Jobs consommés / échecs :
psql "$DATABASE_URL" -c "SELECT count(*) FROM failed_jobs;"
php artisan queue:failed   # liste détaillée (depuis le shell du service)
```

---

## 6. Étape 4 — Vérifier la délivrabilité des emails de bout en bout

### Contexte

- **Render bloque l'egress SMTP** (testé 2026-08-19, #5139) : connect TCP vers
  `smtp.mailgun.org:587/465` → timeout. La bascule validée en prod = **mailer
  `mailgun` (API HTTP, port 443)** : `MAIL_MAILER=mailgun` + `MAILGUN_DOMAIN`,
  `MAILGUN_SECRET`, `MAILGUN_ENDPOINT=api.mailgun.net` (`config/mail.php`).
  ⚠️ render.yaml déclare encore le mailer `smtp` — l'écart `mailgun` est **volontaire**
  (#5139) ; ne pas « corriger » en sens inverse.
- Emails concernés : OTP trial self-service (TrialVerificationMail, #5162), magic
  link trial guided (`CommunicationMail`, #2620/#3002), rappels/alertes du scheduler
  (#3594).

### 6.A — Configuration à vérifier

```bash
# Variables présentes sur les 3 services (web + workers) — dashboard → Environment :
#   MAIL_MAILER=mailgun · MAILGUN_DOMAIN=… · MAILGUN_SECRET=… · MAILGUN_ENDPOINT=api.mailgun.net
# (le worker envoie le magic link, le scheduler les rappels → ne pas oublier les 2 workers.)

# Depuis le shell du web service, le mailer effectif :
php artisan tinker --execute="dump(config('mail.default')); dump(config('mail.mailers.mailgun.domain'));"
```

### 6.B — Test de bout en bout (funnel réel)

```bash
# 1. Trial GUIDED (worker + provisioning + magic link) :
curl -s -X POST https://gestionemployerbackend.onrender.com/api/v1/trial/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"ops-5172-$(date +%s)@<domaine-whitelisté>.com","company":"Test Runbook 5172","country":"DZ","requestedWorkflow":"guided_trial"}'
#   → 200 { status: provisioning_sandbox, provisioning_token: … }

# 2. Poller le statut (≤ 2 min — le job passe pending → ready) :
curl -s "https://gestionemployerbackend.onrender.com/api/v1/trial/status?provisioning_token=<TOKEN>" | jq
#   → { status: "ready", login_url: "/auth/login", … } attendu.

# 3. Trial SELF-SERVICE (OTP) :
curl -s -X POST https://gestionemployerbackend.onrender.com/api/v1/trial/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"ops-5172-otp-$(date +%s)@<domaine-whitelisté>.com","company":"Test OTP 5172","country":"DZ","requestedWorkflow":"self_service"}'
#   → 200 (plus de 503 TRIAL_OTP_SEND_FAILED, #5162).

# 4. L'email arrive dans la boîte de test (webhook/API Mailgun) :
curl -s --user "api:$MAILGUN_SECRET" \
  "https://api.mailgun.net/v3/$MAILGUN_DOMAIN/events?event=accepted&limit=5" | jq '.items[].message.headers.subject'
```

### 6.C — Si un email n'arrive pas

1. **Logs du worker** (dashboard → service → Logs) : chercher
   `Demo access email could not be sent` (échec best-effort du magic link) ou
   `Failed to provision sandbox`.
2. **Mailgun** : tableau de bord → domain → **Logs/Events** (`accepted`/`delivered`/
   `failed`/`complained`).
3. **Sandbox Mailgun** : les domaines de test n'acceptent que les destinataires
   **whitelistés** — ajouter l'adresse de test dans Mailgun → Sending → Domains →
   Sandbox → « Authorized recipients ».
4. `php artisan tinker` (shell web) — envoi de contrôle :
   ```php
   Mail::raw('Test délivrabilité #5172', fn ($m) => $m->to('ops-5172@<whitelisté>.com')->subject('[5172] test mailer'));
   ```

---

## 7. Étape 5 — Vérifications finales (checklist)

| # | Vérification | Commande | Critère |
|---|---|---|---|
| 1 | Santé API | `curl -s …/api/v1/health \| jq '.status, .checks.queue, .checks.redis'` | `ok`, `queue.driver=redis`, `redis.status=pong` |
| 2 | 3 services présents | Dashboard Render → Services | web + queue-worker + scheduler **Live** |
| 3 | Queue consommée | `php artisan queue:monitor redis` (shell worker) | tailles ≤ petites valeurs, décroissantes |
| 4 | Scheduler actif | `php artisan schedule:list` (shell scheduler) | tâches listées ; logs `Running scheduled command` |
| 5 | Jobs échoués | `php artisan queue:failed` / SQL `failed_jobs` | aucun job #5172 non traité |
| 6 | Plus de pending orphelins | SQL `trial_provisionings` (5.D) | `0` ligne > 30 min en pending |
| 7 | Emails | Étape 4 | OTP + magic link reçus |
| 8 | Parité env | `dev-hub/tools/check-render-env-parity.sh --live` (+ snapshots) | exit 0 |

---

## 8. Risques & pièges

- **APP_KEY partagé (#3907)** : les workers référencent `APP_KEY` **depuis le web
  service** (`fromService … envVarKey`). Ne jamais cocher « generateValue » sur un
  worker → sessions/files illisibles entre services.
- **Ne pas toucher `leopardo-db` / `leopardo-redis`** : le blueprint peut les
  afficher lors du sync — ne pas les recréer (perte de données).
- **Plan `starter` obligatoire sur les workers** : le free tier Render suspend les
  workers après 15 min d'inactivité → jobs en attente (symptôme #4948 récurrent).
- **Egress SMTP bloqué** : ne pas revenir à `MAIL_MAILER=smtp` (⚠️ render.yaml le
  déclare encore ; la valeur effective prod est `mailgun` — #5139).
- **Override manuel vs blueprint** : une variable éditée dans le dashboard devient
  un override qui gagne sur render.yaml. Après l'étape 2, vérifier avec
  `check-render-env-parity.sh` que la dérive ne revient pas.
- **Anti-doublon #3951** : ne re-dispatcher une ligne que si elle est bien
  `pending` (index unique partiel `(email) WHERE status='pending'`) — sinon le
  provisioning échouerait sur un doublon ou créerait 2 tenants.
- **QA du 19/08** : le job s'exécutait ponctuellement (worker temporaire ?) —
  si un service worker apparaît déjà sur le compte, vérifier sa **pérennité**
  (plan, autoDeploy, redémarrage) avant de conclure.

---

## 9. Références

- Issue **#5172** (ce runbook) · **#4948** (pending silencieux, sweep/self-healing) ·
  **#5162** (trial self-service 503 OTP) · **#5161** (trial guided failed) ·
  **#5139** (egress SMTP Render → mailer Mailgun) · **#3594** (env mail du scheduler) ·
  **#3774** (Redis interne Render) · **#3907** (APP_KEY partagé) · **#3951** (anti-doublon pending).
- Rapport QA prod : `docs/qa/QA_PROD_2026-08-19.md` (F3/F7 du rapport QA onboarding
  Google 2026-08-20 si présent).
- Outillage : `dev-hub/tools/drain-pending-trial-provisionings.php` ·
  `dev-hub/tools/check-render-env-parity.sh`.
- Commandes existantes de l'app : `trial-provisionings:sweep` (planifiée toutes les
  15 min, `api/bootstrap/app.php`) · `trial:provisioning-sweep` (re-dispatch manuel).

---
*Runbook généré depuis l'issue #5172 — 2026-08-20. Ne pas modifier `render.yaml` sans
manque documenté.*

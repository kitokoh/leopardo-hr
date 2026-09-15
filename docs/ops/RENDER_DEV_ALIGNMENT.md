# 🔄 Runbook — Alignement de l'environnement dev sur `main` (issue #7304)

**Version** : 1.0 · **Date** : 2026-09-15 · **Statut** : actif
**Portée** : environnement **dev** Render (`gestionemployerbackend`, workspace
`africanovatech`) et, par extension, tout environnement interrogé par
`/api/v1/health`. La production (`leopardo-prod`, `render.prod.yaml`) suit un
autre cycle (Release publiée) — voir `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`.

---

## 1. Pourquoi ce runbook

L'audit onboarding du 2026-09-13 (#7304) a montré que **le dev servait un commit
~50 PR en retard sur `main`**. Toute campagne QA menée dessus validait donc du
code périmé : l'assistant d'onboarding « se réouvrait » (le drapeau
`onboarding_completed` n'était pas écrit par l'image déployée alors que le code
de `main` était correct), les notifications ne partaient jamais, et des
correctifs récents étaient déclarés « non vérifiables ».

**Coût d'un environnement dérivé** : une session QA entière, et un faux bug
ouvert contre du code déjà corrigé.

## 2. Règle opérationnelle (à appliquer avant toute recette)

> **Avant toute campagne QA : vérifier que `/health.version` correspond au SHA de
> `main` testé.** Un environnement qui ne correspond pas n'est pas un
> environnement de recette.

```bash
# comparaison explicite (SHA attendu)
dev-hub/tools/check-deploy-drift.sh \
  --url "${DEV_API_BASE_URL:-https://gestionemployerbackend.onrender.com}" \
  --expect origin/main --label dev
```

Sorties : `0` = aligné, `1` = **dérive** (le script nomme les deux SHAs),
`2` = erreur technique (URL injoignable, `/health` sans champ `version`).
La même garde tourne en CI (`.github/workflows/deploy-drift-guard.yml`, toutes
les 30 min + `workflow_dispatch`).

> **Référence « déployable »** : l'API n'est redéployée que sur changement `api/**`
> (`deploy-main.yml`). En CI, la garde compare donc à la version servie au
> **dernier commit touchant `api/`** (résolu par API) — sinon chaque merge
> docs/web-only produirait une alerte de dérive qui n'en est pas une. En local,
> `--expect <sha>` reste prioritaire : pour un pré-vol de recette, passez le SHA
> du commit `api/` que vous voulez voir servi (`git log -1 --format=%H origin/main -- api`).

## 3. État vérifié le 2026-09-15 (avant/après correctif)

| Contrôle | Avant | Après |
|---|---|---|
| `GET /api/v1/health` (dev) → `version` | `fe2ab9f` (= PR #7383, 2026-09-14T14:12Z) | `b491ed3` = **HEAD de `main`** |
| Dernier deploy Render live | 2026-09-14T14:39Z | 2026-09-15T03:07Z (déclenché via API) |
| `autoDeploy` du service dev | `no` | **`no` — inchangé** (décision #6700, voir §5.2) |
| `queue.notifications` (dev) | 19 en attente | voir §6 |
| `queue.failed_jobs` (dev) | 16 | voir §6 |
| check `redis` (dev) | `degraded` (`ConnectionException`) | voir §7 |

## 4. Cause racine de la dérive (mesurée, pas déduite)

Le service dev n'a **pas** d'auto-deploy Render, et le déploiement continu est
censé venir du workflow `deploy-main.yml` (topologie : « dev = continu, push sur
`main` »). Or ce workflow **saute** son job de déploiement :

```
##[warning]Deploy skipped — conclusion "Tests - Leopardo RH" = null
  pour le SHA 2b572f2edcca1c58d8f169e8f9f3d5e59634611b.
Deploy API + Web to Render => skipped
```

Le gate `verify-deploy-workflows` exige la conclusion du run `Tests - Leopardo RH`
pour le SHA déployé ; sous charge (runs `synchronize` non créés — leçon #3545
d'`AGENTS.md`), ce run est **absent** → `Tests=missing` → déploiement **sauté**,
vert dans l'UI. Douze runs « Deploy - Leopardo RH » successifs (2026-09-14
09:25 → 19:27) ont donc tous été **des non-déploiements** : `main` avançait, le
dev restait figé sur `fe2ab9f`.

**Conséquence de second ordre** : l'image figée précédait les correctifs de
worker (`api/docker-entrypoint.sh`, issues #7041 et QA 2026-09-14). Le dev
n'avait donc **aucun worker de queue actif** → `notifications: 19`,
`failed_jobs: 16`.

## 5. Remettre l'environnement dev sur `main`

1. **Déclencher un déploiement manuel** (API Render, immédiat) :

   ```bash
   curl -s -X POST -H "Authorization: Bearer $RENDER_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"clearCache":"do_not_clear"}' \
     "https://api.render.com/v1/services/$SERVICE_ID/deploys"
   ```

   `$SERVICE_ID` du dev : `srv-d7dro8u7r5hc73a395pg`.
   (Dashboard : service → *Manual Deploy* → branche `main`.)

2. **Ne PAS activer l'auto-deploy** du service dev : c'est une décision déjà
   prise dans le dépôt (#6700) — `render.yaml` porte `autoDeploy: false` parce
   qu'un deploy automatique redéclenche un build à **chaque** push `main`, y
   compris docs/web-only, et consomme les ~500 h de build gratuites. Le levier
   reste `deploy-main.yml` (via `RENDER_DEPLOY_HOOK_URL`) — dont le gate peut
   sauter (voir §5.2). Le filet est donc la **garde** (§2) + un redéploiement
   **à la demande** (§5.1), pas un auto-deploy permanent.

3. **Vérifier** : `dev-hub/tools/check-deploy-drift.sh --expect origin/main`
   doit sortir `0` et afficher le SHA de `main`.

> ⚠️ Un deploy Render prend le **HEAD de la branche**, jamais un commit arbitraire
> (limite API documentée dans `RENDER_DEV_PROD_TOPOLOGY.md`) : ne pas croire
> qu'un `POST /deploys` épingle un SHA.

## 5.2 Pourquoi le dev peut redériver malgré tout (et ce qui reste à corriger)

Le déploiement continu est porté par `deploy-main.yml`, qui **saute** son job de
déploiement quand le run `Tests - Leopardo RH` du SHA est introuvable
(`Tests=missing` — runs non créés sous charge, leçon #3545). Constaté le
2026-09-14 : **12 runs `success` consécutifs, aucun déploiement**.

Ce défaut de gate est **suivi par #7457** (issue dédiée) : la garde §2 **détecte**
la dérive, elle ne la répare pas. En attendant, la règle §2 (pré-vol de recette)
et le redéploiement manuel §5.1 restent les deux gestes opérationnels.

> ⚠️ Ne pas « corriger » la dérive en réactivant `autoDeploy` : cela échange un
> silence contre une facture de build (#6700).

## 6. Worker de queue en dev

Le dev est en **mono-conteneur** : `api/docker-entrypoint.sh` lance
`php artisan queue:work` en arrière-plan (boucle de respawn, `--max-time=3600`,
#7041) — **aucun service worker séparé n'est nécessaire ni possible** :

```
POST /v1/services/{id}/jobs  →  {"message":"new paid services not allowed"}
```

(Le compte dev refuse toute création de service payant : ni `background_worker`,
ni `cron job`, ni job one-off. Le worker dédié `leopardo-queue-worker` n'existe
que côté **prod**, `render.prod.yaml`.)

Conséquence pratique : **tant que l'image dev est à jour, la queue se draine
seule**. Si `queue.notifications` ne retombe pas à 0 après un déploiement :

1. vérifier que `/health.version` est bien à jour (§3) — un worker « absent » est
   presque toujours un worker d'**image périmée** ;
2. lire les logs Render du service : le worker écrit désormais sur `stderr`
   (`process.ERROR:`), donc les échecs de jobs sont diagnosticables ;
3. **purge des `failed_jobs`** : elle exige un shell sur le conteneur
   (`php artisan queue:flush`) ou une connexion à la base Neon — **action
   propriétaire** (aucun endpoint API ne l'expose, et il n'y a pas de worker
   dédié en dev). Les jobs en échec ne sont pas rejoués automatiquement.

## 7. Dérive annexe connue : `redis` dégradé en dev

`GET /health` (dev) rapporte `redis: degraded / ConnectionException` alors qu'une
instance Key Value existe (`leopardoai`, plan free) dans le compte dev : la
variable `REDIS_URL` n'est pas posée sur le service. La queue utilise
`QUEUE_CONNECTION=database` (stratégie unique #5578), donc **le drainage n'en
dépend pas**, mais cache/session Redis restent indisponibles. Correctif : poser
`REDIS_URL` (+ `REDIS_CLIENT=predis`) sur le service dev depuis le dashboard,
puis revérifier le check `redis`.

## 7bis. Contrôle de la production (Release déployée)

La prod ne publie pas un SHA mais une **version de release** (`APP_VERSION`,
ex. `4.32.0`) : la comparaison par commit y est impossible par construction. Le
contrôle utile est « **la dernière Release publiée est-elle déployée ?** » :

```bash
# dernier tag publié (API GitHub, pas de clone nécessaire)
curl -s -H "Authorization: Bearer $GH_TOKEN" \
  "https://api.github.com/repos/kitokoh/leopardo-hr/releases/latest" | jq -r .tag_name

dev-hub/tools/check-deploy-drift.sh \
  --url https://leopardo-prod.onrender.com --expect v4.32.0 --label prod
```

Le script tolère le préfixe `v` (`v4.32.0` ↔ `4.32.0`) et compare des
**versions** dès que la référence en est une — sans la résoudre comme une ref git
(`git rev-parse v4.32.0` pointerait sur le commit du tag, ce qui faussait le
contrôle). Résultat observé le 2026-09-15 : prod sert bien `4.32.0` (= dernier
tag), `queue: 0`, `failed_jobs: 0`.

La garde CI fait ce contrôle automatiquement (matrice `dev` + `prod`) : en prod,
la référence est le **dernier tag publié**, donc « une Release n'a pas été
déployée » est détecté au lieu d'être déclaré incomparable.

## 8. Ce que cette garde ne fait pas

- Elle ne **corrige** pas la dérive : elle la rend visible (script + CI).
- Elle ne remplace pas le gate de déploiement : elle mesure l'**effet** (ce qui
  est réellement servi), là où `deploy-main.yml` mesure l'**intention**.
- Elle n'exige pas que la prod publie un SHA : les environnements versionnés par
  release (`4.31.0`) utilisent `--allow-release` (comparaison déclarée
  impossible, alignement à confirmer par le runbook de release).

## 9. Liens

- Issue : #7304 · Garde : `.github/workflows/deploy-drift-guard.yml`
- Script : `dev-hub/tools/check-deploy-drift.sh`
- Topologie dev/prod : `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`
- Workers & queue : `docs/ops/RENDER_QUEUE_WORKERS.md`, `docs/ops/HEALTH_ENDPOINTS.md`

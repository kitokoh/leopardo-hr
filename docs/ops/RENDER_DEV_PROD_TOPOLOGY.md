# Topologie de déploiement Render — dev vs prod

## Contexte

Historiquement, `render.yaml` déployait un unique groupe de services à
chaque push sur `main`, configuré avec `APP_ENV=production` et des clés de
paiement réelles (Stripe/Chargily). Il n'existait aucune séparation entre
un environnement de développement continu et une production stable : tout
merge sur `main` atteignait directement ce qui était nommé (et configuré
comme) la production, et les tags de version (`v4.24.0`, `v4.25.0`, ...)
ne déclenchaient qu'une création de changelog GitHub (`release.yml`),
jamais un déploiement.

Le domaine `leopardo-rh.com` n'étant pas encore acheté au moment de ce
chantier, aucun trafic client réel ne dépendait de cet environnement — ce
qui a permis de le requalifier sans opération de bascule à chaud.

## Nouvelle topologie

| Tier | Déclencheur | Fichier | Workflow | Surfaces | Coût |
|------|-------------|---------|----------|----------|------|
| **dev** (continu) | push sur `main` | `render.yaml` | `deploy-main.yml` | API Render `gestionemployerbackend` + web Vercel `leopardo` (intégration Git) + admin CF Pages `leo-admin` | Plan existant (Starter), inchangé |
| **prod** (stable) | **GitHub Release publiée** (tag `vX.Y.Z` poussé → `release.yml` crée la Release → `deploy-prod.yml` sur `release: published`) | `render.prod.yaml` | `deploy-prod.yml` | API Render `leopardo-prod` + Neon + web Vercel `leopardo-prod` + admin CF Pages `leo-admin-prod` | Phase 1 : tier gratuit (web + Key Value) + Neon Postgres |

> Le déclencheur « Release publiée » (au lieu du push de tag brut) rend la
> livraison prod visible dans l'UI GitHub (une Release = une livraison) et
> évite les doubles runs. Les jobs `deploy-web-prod` (Vercel) et
> `deploy-admin-prod` (Cloudflare Pages) s'exécutent APRÈS le job API : un
> frontend qui cible l'API prod n'est promu que si l'API prod est saine.
>
> ⚠️ **Sémantique du code déployé (limite API Render)** : `POST
> /services/{id}/deploys` déploie la HEAD de `main`, pas le commit du tag. Le
> gate `verify` exige donc **tag == HEAD de `main`** en déclenchement
> automatique ; en `workflow_dispatch` sur un tag plus ancien, un
> avertissement est émis (l'API recevra le code de main, les frontends le
> commit du tag). Toujours créer la Release sur le commit HEAD de `main`.
>
> **Migrations DB au boot (vérifié 2026-09-04)** : `Dockerfile.prod` pose
> `RUN_MIGRATIONS=true` ; `docker-entrypoint.sh` migre `public` puis
> `shared_tenants` au démarrage (bootstrap idempotent, retries 3×, verrou
> `--isolated`, catch-up course 42P07). Avant un tag sur schéma sensible :
> backup Neon (plan payant) puis healthcheck post-déploiement (déjà câblé).
>
> **Rollback** : API → rollback Render vers le deploy live précédent (HTTP
> 400/409 = warning : cible déjà live, non bloquant). Frontends (Vercel/CF
> Pages) : pas de rollback auto — un échec avant promote laisse l'ancien
> déploiement en place ; après promote, re-publier la Release précédente.

### ⚠️ Dérive constatée entre `render.yaml` (dev) et la config réelle

> ✅ **Corrigée le 2026-09-05 (PR #6831)** : `render.yaml` a été réécrit
> pour refléter la réalité (entrée Postgres `leopardo-db` supprimée,
> `DB_URL` sync: false, CACHE/SESSION `file` par défaut avec probe
> d'entrypoint, Redis branché dans le dashboard). Les constats ci-dessous
> sont conservés pour l'historique.

En vérifiant la config live du service dev (`gestionemployerBackend`,
workspace Render `africanovatech`) avant de répliquer son schéma en prod,
constat empirique (pas une supposition) :

- `render.yaml` déclarait un Postgres géré par Render (`databases:
  leopardo-db`) — **ce Postgres n'existe pas** dans le workspace dev
  (`GET /v1/postgres` → liste vide). La base réelle est `DB_URL` pointé
  manuellement vers **Neon** (projet `leopardorh`, host
  `...eu-west-2.aws.neon.tech`).
- `render.yaml` déclarait Redis (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
  `fromDatabase: leopardo-redis`) — la config live utilisait en réalité
  `CACHE_STORE=file` et `SESSION_DRIVER=file` (pas de Redis actif),
  bien qu'une instance Key Value existe (`leopardoai`). Re-vérification
  2026-09-05 (healthcheck public) : le check `redis` répond `pong` sur le
  dev → un Redis est configuré côté dashboard (latence ~94 ms, type
  d'instance à confirmer — interne ou externe).

`render.yaml` (dev) n'avait pas été corrigé dans le chantier initial (hors
périmètre), laissant le fichier non fiable comme source de vérité ; c'est
désormais fait (PR #6831).

### Pourquoi `render.yaml` n'a pas été renommé

Renommer les `name:` de service dans `render.yaml` aurait fait perdre à
Render le mapping avec les services déjà déployés : Render aurait recréé
de nouveaux services au lieu de renommer les existants, laissant les
anciens orphelins (facturés, inutilisés). Le fichier reste donc identique
sur ce point ; seule son rôle (dev/test continu) est désormais documenté
en tête de fichier.

### Phase 1 — tier gratuit pour `render.prod.yaml`

Limite constatée (docs Render 2026) : les services de type **Background
Worker ne sont pas éligibles au tier gratuit** (seuls Web Service, Postgres
et Key Value le sont). `render.prod.yaml` ne provisionne donc, pour cette
phase de validation, que :

- le service web (API)
- Key Value (Redis-compatible, en mémoire uniquement)

La base de données n'est **pas** un Postgres Render : après avoir constaté
que le Postgres gratuit Render expire au bout de 30 jours, et que le dev
utilise déjà Neon en pratique (voir dérive ci-dessus), la prod a été
alignée sur le même schéma — **Neon Postgres** (projet `LEOPARDO`, branche
`production`, région `eu-central-1`), câblé via `DB_URL` (`sync: false`).
Le Postgres Render initialement créé pour Phase 1 a été supprimé après
validation croisée (healthcheck OK sur Neon).

**`leopardo-queue-worker` et `leopardo-scheduler` ne sont pas encore
répliqués en prod** — nuance importante (vérifiée code + healthchecks
2026-09-05) : le conteneur web (Dockerfile.prod → docker-entrypoint.sh)
draine la queue en **mono-conteneur** (`php artisan queue:work` en
arrière-plan, connexion `database`) : les jobs asynchrones (notifications,
emails, PDF, paie) SONT traités en Phase 1, y compris côté prod. Ce qui ne
tourne PAS en Phase 1, faute de service `leopardo-scheduler` (inéligible au
tier gratuit) : les **tâches planifiées** (`schedule:run` — accrual congés,
relances contrat/billing, réconciliation recouvrement…). À provisionner sur
un plan payant (Starter, ~7 $/mois) avant tout trafic client réel.

### Suite recommandée avant un vrai lancement client

1. Valider que `deploy-prod.yml` déclenche correctement le déploiement des
   trois surfaces (API Render + web Vercel + admin CF Pages) et les
   healthchecks sur un tag (le run du 2026-09-04 sur `v4.27.2` sert de
   recette ; ensuite un tag de test `vX.Y.Z-rc*` à chaque évolution).
2. Acheter le nom de domaine et le plan Render payant.
3. Ajouter `leopardo-queue-worker` et `leopardo-scheduler` à
   `render.prod.yaml` sur un plan payant.
4. Passer le projet Neon `LEOPARDO` (actuellement plan gratuit) sur un
   plan payant avant tout trafic réel (sauvegardes, quotas de calcul).
5. Renseigner les secrets réels dans l'environnement Pulumi ESC
   `solarnyxss/leopardo-hr/prod` (`pulumi env set ... --secret`), après
   rotation des clés Render/Stripe/Chargily déjà partagées en clair.
6. Une fois la prod validée en continu, envisager un renommage progressif
   des services `render.yaml` (dev) pour lever toute ambiguïté de nommage.

## Secrets et variables GitHub Actions requis

`deploy-prod.yml` échoue explicitement (fail-closed, cohérent avec le reste
du repo — cf. issues #4524/#4720) si l'un des éléments suivants manque. Rien
n'est pré-rempli automatiquement : ces valeurs doivent être saisies après
rotation des clés Render déjà partagées en clair dans ce chat.

**Secrets** (`Settings > Secrets and variables > Actions > Secrets`) :

| Secret | Rôle |
|---|---|
| `RENDER_PROD_API_KEY` | Clé API Render du workspace prod ("ALI MAHADI's workspace") — utilisée pour déclencher le déploiement, interroger son statut et effectuer un rollback réel via l'API (pas un simple deploy hook) |
| `RENDER_PROD_SERVICE_ID` | (ajout 2026-09-03, #6807) ID du service web `leopardo-prod` — lu en secret (l'onglet Variables ne l'avait pas) |
| `PROD_RENDER_API_BASE_URL` | URL publique du service prod (`https://leopardo-prod.onrender.com`), healthcheck post-déploiement + base `VITE_API_URL` du build admin |
| `VERCEL_PROD_TOKEN` | Jeton API Vercel du compte prod (ibrahimkoubaye) — CLI `vercel` (pull/build/deploy) |
| `VERCEL_PROD_ORG_ID` | ID d'équipe Vercel prod (`team_EWSaydzjiTNOT4JlQEXRyrpa`) |
| `VERCEL_PROD_PROJECT_ID` | Projet Vercel prod (`prj_Q2UQ7h5SeozRB2fVttHRAAq8PTdc`, `leopardo-prod`) |
| `CLOUDFLARE_PROD_API_TOKEN` | Jeton API Cloudflare du compte prod (Pages:Edit) |
| `CLOUDFLARE_PROD_ACCOUNT_ID` | Compte Cloudflare prod (`30540c0bc96ca1925a0d4d041b98d098`) |
| `CLOUDFLARE_API_TOKEN` / `CLOUDFLARE_ACCOUNT_ID` | (dev, ajout 2026-09-04) compte Cloudflare dev (`28a39f5640d9a5b9a5f0a86ec7ef5111`) — active le chemin GitHub Actions de `deploy-admin-dashboard.yml` (leo-admin) |

Le `DB_URL` (connexion Neon) est configuré directement comme variable
d'environnement `sync: false` sur le service Render (pas un secret GitHub
Actions) — valeur dans Pulumi ESC `solarnyxss/leopardo-hr/prod`
(`neon.databaseUrl`).

**Variables** (`Settings > Secrets and variables > Actions > Variables`) :

| Variable | Rôle |
|---|---|
| `RENDER_PROD_SERVICE_ID` | (doublon historique, lu désormais en secret) ID du service web `leopardo-prod` (`srv-dacsr6gae00c73ddk150`) |
| `PROD_WEB_PROD_URL` | URL web prod (`https://leopardo-prod.vercel.app`) — lien `environment.url` |
| `PROD_ADMIN_PROD_URL` | URL admin prod (`https://leo-admin-prod.pages.dev`) — lien `environment.url` |

Ces noms sont volontairement distincts des secrets/variables déjà utilisés
par `deploy-main.yml` (`RENDER_DEPLOY_HOOK_URL`, `RENDER_ROLLBACK_HOOK_URL`,
`vars.PROD_API_BASE_URL`) pour éviter toute collision avec l'environnement
dev/continu existant.

**Ressources déjà provisionnées (2026-09-03)**, workspace prod Render
(`tea-da9svvpsrm7s73d8o2j0`) et organisation Neon (`org-shiny-snow-15524747`,
"ALI MAHADI") :
- Service web `leopardo-prod` (`srv-dacsr6gae00c73ddk150`, URL
  `https://leopardo-prod.onrender.com`) — recréé sous ce nom pour un
  sous-domaine propre (le slug Render est figé à la création : un simple
  renommage du service `gestionemployerbackend-prod` d'origine
  (`srv-dacr2j6k1f9s73adbj7g`, supprimé) ne changeait pas l'URL)
- Key Value `leopardo-redis-prod` (`red-dacr1rfavr4c739gtan0`)
- Postgres Neon — projet `LEOPARDO` (`rough-cherry-23234590`), branche
  `production` (`br-broad-forest-b18ek61g`), région `eu-central-1`.
  Le Postgres Render `leopardo-db-prod` initialement créé pour Phase 1 a
  été **supprimé** après migration validée vers Neon (healthcheck OK) —
  il n'apparaît plus dans `render.prod.yaml`.

Les clés Render et Neon partagées en clair dans la conversation ont été
utilisées pour cette création initiale ; elles doivent être régénérées
avant tout lancement public, conformément au plan de bascule ci-dessus.

### Surfaces web prod provisionnées (2026-09-04, issue #6808)

- **Web client (Vercel)** — projet `leopardo-prod`
  (`prj_Q2UQ7h5SeozRB2fVttHRAAq8PTdc`, équipe `ibrahimkoubaye-6514`,
  `team_EWSaydzjiTNOT4JlQEXRyrpa`, racine `front/web`). Variables posées
  (target production) : `NEXT_PUBLIC_API_URL=https://leopardo-prod.onrender.com/api/v1`,
  `LEOPARDO_API_URL=https://leopardo-prod.onrender.com`,
  `NEXT_PUBLIC_SITE_URL=https://leopardo-prod.vercel.app`,
  `NEXT_PUBLIC_ENVIRONMENT=production`, `NEXT_PUBLIC_ENABLE_BLOG=true`.
  Déploiement piloté par la CLI Vercel dans `deploy-prod.yml` (pull → build
  → deploy `--prebuilt --prod`) — l'intégration Git du projet n'est pas
  utilisée (prod = tags uniquement).
- **Admin dashboard (Cloudflare Pages)** — projet `leo-admin-prod`
  (compte Cloudflare prod `30540c0bc96ca1925a0d4d041b98d098`, domaine
  `leo-admin-prod.pages.dev`), build `VITE_API_URL` → API prod, upload
  direct wrangler dans `deploy-prod.yml` (même mécanique que `leo-admin`
  côté dev).
- **CORS/Sanctum API prod** (posés sur le service Render `leopardo-prod`,
  valeurs `sync: false` côté dashboard) :
  `CORS_EXTRA_ORIGIN`/`ADMIN_DASHBOARD_URL=https://leo-admin-prod.pages.dev`,
  `FRONTEND_URL=https://leopardo-prod.vercel.app`,
  `SANCTUM_STATEFUL_DOMAINS` incluant `leopardo-prod.onrender.com`,
  `leopardo-prod.vercel.app`, `leo-admin-prod.pages.dev` + localhost.

**Source de vérité recommandée :** l'environnement Pulumi ESC
`solarnyxss/leopardo-hr/prod` (créé dans ce chantier) contient déjà la
structure attendue pour ces valeurs et pour les secrets applicatifs
(Stripe, Chargily, Google, Firebase, mail, admin). Un mainteneur ayant
accès à l'organisation Pulumi peut les résoudre avec :

```bash
pulumi env open solarnyxss/leopardo-hr/prod
```

puis les recopier dans les secrets/variables GitHub ci-dessus. Une
synchronisation automatisée ESC → secrets GitHub (script ou action dédiée)
pourra être ajoutée une fois ces valeurs réelles disponibles.

## État vérifié le 2026-09-05 (audit déploiement dev/prod)

Revue indépendante des surfaces live + de la cohérence déclaratif/réel.
Constat : **les deux tiers sont en ligne et sains**, avec des écarts
d'architecture résiduels listés ci-dessous.

### Surfaces vérifiées (HTTP, 2026-09-05 ~18:41 UTC)

| Surface | URL | État |
|---|---|---|
| API dev (continu) | `https://gestionemployerbackend.onrender.com/api/v1/health` | HTTP 200 — DB ok, redis pong (~94 ms), storage ok, queue `database` (driver), version 4.24.0 |
| API prod | `https://leopardo-prod.onrender.com/api/v1/health` | HTTP 200 — DB ok (Neon), redis pong (~3 ms, KeyValue interne), queue vide, `failed_jobs` 0 |
| Web dev | `https://gestionemployer-backend.vercel.app` (+ `https://leopardo.vercel.app`, 200 aussi — doublon Vercel à clarifier) | 200 |
| Web prod | `https://leopardo-prod.vercel.app` | 200 |
| Admin dev | `https://leo-admin.pages.dev` | 200 |
| Admin prod | `https://leo-admin-prod.pages.dev` | 200 |
| Site marketing | `https://kitokoh.github.io/leopardo-hr/` (Pages depuis main, #6827) | 200 |
| Domaine réservé | `https://leopardo-rh.com` | injoignable (non acheté/pointé — attendu Phase 1) |

### Chaîne de déploiement

- **dev** (`deploy-main.yml`) : gate fonctionnel (poll des runs Tests/Web CI
  du SHA + garde anti-SHA-stale). Dernier déploiement API réel : 2026-09-02
  21:20 (commit 760f79d4). Depuis, les merges rapides + tests longs font
  skiper le job `deploy-api` (SHA dépassé par un push plus récent, ou
  pushes sans changement `api/**` → skip légitime). **L'API dev accumule du
  retard sur main** ; rattrapage possible via `workflow_dispatch` de
  `deploy-main.yml` sur main (le gate contourné en dispatch).
- **prod** (`deploy-prod.yml`) : recette validée en workflow_dispatch le
  2026-09-04 06:05 (3 surfaces déployées + healthchecks OK). La chaîne
  automatique « tag vX.Y.Z → release.yml → Release publiée → deploy-prod »
  n'a PAS encore tourné de bout en bout (run release.yml du tag v4.27.2
  annulé le 2026-09-03 21:00, pas de Release créée). À valider sur un
  prochain tag (créer la Release sur HEAD de main, checks requis verts).
- Secrets prod : présents dans l'environnement GitHub `production`
  (8 secrets RENDER_PROD_*/VERCEL_PROD_*/CLOUDFLARE_PROD_* + base URL).

### Écarts résiduels (actions humaines requises)

1. **Sauvegardes DB : désactivées** — `database-backup.yml` tourne chaque
   nuit mais l'étape `pg_dump` est skippée (secrets `DATABASE_URL`,
   `BACKUP_S3_BUCKET`, `AWS_*` absents). Neon étant sur plan gratuit (pas de
   sauvegarde intégrée), la base prod n'a **aucune protection** → poser les
   secrets ou passer Neon en plan payant (backups PITR). P0.
2. **Drain de secours GH** (`queue-worker-fallback.yml`, toutes les 5 min) :
   inerte (mêmes secrets DB/APP_KEY absents) ; garde anti-faux-vert ajoutée
   (PR #6831) → skip explicite au lieu d'un faux succès. Les workers
   mono-conteneur Render drainent en conditions normales.
3. **Supervision queue prod** (`queue-supervision.yml`) : skip explicite
   (secrets absents) — activer avec les mêmes secrets une fois posés.
4. **Plan payant + scheduler prod** : ajouter `leopardo-scheduler` (+
   `leopardo-queue-worker` si on sort du mono-conteneur) sur plan Starter.
5. **Neon prod** : passer le projet `LEOPARDO` en plan payant avant trafic
   réel.
6. **Rotation des clés** Render/Neon/Stripe/Chargily partagées en clair
   dans les conversations (cf. section Secrets) — P0 sécurité.
7. **SUPER_ADMIN_PASSWORD** : vérifier qu'il est posé sur les services
   Render dev et prod (sinon login admin impossible — seeder génère un mot
   de passe aléatoire non récupérable).
8. **Domaine** `leopardo-rh.com` : achat + DNS + plans payants Render/Vercel/
   Cloudflare avant tout lancement client.

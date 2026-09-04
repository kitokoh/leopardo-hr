# DEPLOYMENT_URLS.md — URLs de déploiement actives (source : registre DOMAINS.md)

> Livrable de l'épic #3765 (stabilisation production). Documente les URLs
> **réellement actives** (services gratuits Render/Vercel/Cloudflare Pages),
> les endpoints de santé et la procédure de déploiement.
> Source de vérité des domaines : `docs/ops/DOMAINS.md` (garde
> `check-canonical-domains.sh`).

## Surfaces actives (live)

| Surface | URL | Service | Vérifié |
|---|---|---|---|
| API Laravel (base `/api/v1`) | `https://gestionemployerbackend.onrender.com` | Render (gratuit) | HTTP 200 2026-08-15 |
| Santé API | `https://gestionemployerbackend.onrender.com/api/v1/health` | Render | — |
| Vitrine / portail web | `https://gestionemployer-backend.vercel.app` | Vercel (gratuit) | HTTP 200 2026-08-15 |
| Admin plateforme (super-admin) | `https://leo-admin.pages.dev` | Cloudflare Pages | CORS/Sanctum de référence (#3766) |

## Services Render (backend)

- **API + Web** : `gestionemployerbackend` — déployé via webhook
  `RENDER_DEPLOY_HOOK_URL` (secret GitHub, workflow `deploy-main.yml`).
- **Queue worker** : mono-conteneur — le worker tourne en arrière-plan du conteneur web
  (entrypoint, driver `database`) ; **drain de secours GitHub Actions** toutes les 5 min
  (`queue-worker-fallback.yml`, #5205). #4948 résolu — plus de service séparé attendu.
- **Drivers (boot)** : `QUEUE_CONNECTION=database` fixe ; `CACHE_STORE`/`SESSION_DRIVER`
  automatiques (Redis si Upstash répond, sinon `file`) via `infra:probe-availability` (#5206/#5207).
- **Staging** : `RENDER_STAGING_DEPLOY_HOOK_URL` (fallback hook prod si absent) ;
  URL d'health-check staging : `STAGING_API_URL`
  (défaut `https://gestionemployerbackend.onrender.com`).
- **Rollback** : `RENDER_ROLLBACK_HOOK_URL` (déclenché en cas d'échec de deploy prod).

## Surfaces prod (topologie tag `vX.Y.Z` — `deploy-prod.yml`)

Vraie production, promue uniquement par un tag git validé (checks requis
verts, tag ancêtre de `main`). Registre : `docs/ops/DOMAINS.md`.

| Surface | URL | Service | Déployé par |
|---|---|---|---|
| API Laravel prod (base `/api/v1`) | `https://leopardo-prod.onrender.com` | Render prod (web service `leopardo-prod`, `srv-dacsr6gae00c73ddk150`) + Neon (projet `LEOPARDO`, branche `production`) | job `deploy-prod` |
| Vitrine / portail web prod | `https://leopardo-prod.vercel.app` | Vercel prod (projet `leopardo-prod`, équipe ibrahimkoubaye-6514) — `front/web` buildé avec `NEXT_PUBLIC_API_URL` → API prod | job `deploy-web-prod` |
| Admin plateforme prod | `https://leo-admin-prod.pages.dev` | Cloudflare Pages prod (projet `leo-admin-prod`, compte prod) — `front/admin-dashboard` buildé avec `VITE_API_URL` → API prod | job `deploy-admin-prod` |

Secrets GitHub requis (fail-closed) : `RENDER_PROD_API_KEY`,
`RENDER_PROD_SERVICE_ID`, `PROD_RENDER_API_BASE_URL`,
`VERCEL_PROD_TOKEN`, `VERCEL_PROD_ORG_ID`, `VERCEL_PROD_PROJECT_ID`,
`CLOUDFLARE_PROD_API_TOKEN`, `CLOUDFLARE_PROD_ACCOUNT_ID`.
Variables GitHub : `RENDER_PROD_SERVICE_ID`, `PROD_WEB_PROD_URL`,
`PROD_ADMIN_PROD_URL` (liens `environment.url`, cosmétique).

Côté API prod, `CORS_EXTRA_ORIGIN`/`ADMIN_DASHBOARD_URL` =
`https://leo-admin-prod.pages.dev`, `FRONTEND_URL` =
`https://leopardo-prod.vercel.app` (posés sur le service Render prod).

## Déclenchement d'un déploiement

```bash
# Déploiement API/Web (Render) — via le hook secret (depuis GitHub Actions)
curl -X POST "$RENDER_DEPLOY_HOOK_URL"

# Vérification post-déploiement
curl -s https://gestionemployerbackend.onrender.com/api/v1/health
curl -s https://gestionemployerbackend.onrender.com/api/v1/health/ready
```

La version API attendue sur main : `APP_VERSION` = **4.24.0+**
(`api/config/app.php`, défaut `env('APP_VERSION', '4.24.0')`).
Critère de succès #3765 : `/api/v1/health` retourne v4.24.0+.

## Variables d'environnement liées au déploiement

| Variable | Usage | Valeur de référence |
|---|---|---|
| `RENDER_DEPLOY_HOOK_URL` | Webhook deploy prod | secret GitHub |
| `RENDER_STAGING_DEPLOY_HOOK_URL` | Webhook deploy staging | secret GitHub (fallback prod) |
| `RENDER_ROLLBACK_HOOK_URL` | Webhook rollback prod | secret GitHub |
| `STAGING_API_URL` | Health-check staging / lien `environment.url` | `https://gestionemployerbackend.onrender.com` |
| `LEOPARDO_API_URL` | Base API utilisée par la vitrine Next.js | résolue via `resolveBackendBaseUrl()` (défaut Render) |
| `FRONTEND_URL` / `APP_URL` | URLs canoniques CORS/Sanctum | `https://gestionemployer-backend.vercel.app` / `https://gestionemployerbackend.onrender.com` |
| `NEXT_PUBLIC_ENABLE_BLOG` | Activation du blog vitrine (#2906) | `true` (contenu prêt, activé 2026-08-18) |

## Domaines réservés (NXDOMAIN — NE PAS utiliser en default de build)

`leopardo-rh.com`, `www.leopardo-rh.com`, `app.leopardo-rh.com`,
`admin.leopardo-rh.com`, `api.leopardo-rh.com`, `docs.leopardo-rh.com`,
`api.leopardo.app`, `proxy.leopardo-rh.com`, `demo.leopardo-rh.com`…
→ voir `docs/ops/DOMAINS.md` (registre complet) et #3452 (provisionnement DNS).

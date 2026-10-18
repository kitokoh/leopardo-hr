# Procédure de bascule de stack — une seule action

> **Issue #8707.** Ce document remplace la procédure manuelle « rebuilder les
> fronts puis reconfigurer les variables GitHub ». Il existe parce qu'une bascule
> a coûté un mois de front figé (2026-09-20 → 2026-10-18) : le bundle admin
> portait une URL Render brute, et personne n'a vu que la stack A était
> suspendue.

## Principe : une URL publique qui ne change JAMAIS

Avant #8707, chaque front embarquait l'URL du fournisseur au build :

| Front | Ce qui était figé dans le bundle | Conséquence d'une bascule |
|---|---|---|
| Web (Next/Vercel) | `NEXT_PUBLIC_API_URL` | rebuild + redéploiement |
| Admin (Vite/Cloudflare Pages) | `VITE_API_URL` = URL Render brute | rebuild + redéploiement |

Désormais, le navigateur ne parle **plus jamais** à un fournisseur. Il parle à
un **proxy same-origin stable**, et c'est le proxy qui choisit la stack :

```
navigateur
   │  (URL stable, jamais re-provisionnée)
   ▼
PROXY  ──►  API de la stack ACTIVE
   ▲
   └── cible lue AU RUNTIME : API_PROXY_TARGET > BACKEND_API_URL > NEXT_PUBLIC_API_URL
```

Deux proxys, un par tier :

| Tier | Proxy stable | Projets qui l'appellent |
|---|---|---|
| DEV | `https://gestionemployer-backend.vercel.app/api/v1` | web dev (même origine) + admin dev (`leo-admin.pages.dev`) |
| PROD | `https://leopardo-prod.vercel.app/api/v1` | web prod (même origine) + admin prod (`leo-admin-prod.pages.dev`) |

Le web l'appelle en relatif (`/api/v1`), l'admin en absolu (il est sur un autre
domaine, `*.pages.dev`). Dans les deux cas **l'URL ne change jamais**.

## Bascule : UNE action

```bash
# Basculer le DEV vers la stack B (exemple)
gh variable set DEV_ACTIVE_STACK --body b
gh variable set DEV_API_BASE_URL  --body https://leopardo-dev-b.onrender.com/api/v1
```

Puis **une seule** variable d'environnement Vercel sur le projet proxy :

```bash
# Projet « leopardo » (prj_RcRVAujaFul8WNZXxgnNKTb9lXdr) — tier DEV
vercel env rm  API_PROXY_TARGET production --yes
vercel env add API_PROXY_TARGET production <<< "https://leopardo-dev-b.onrender.com/api/v1"
vercel deploy --prod
```

Ou, dans l'interface : **Vercel → projet `leopardo` → Settings → Environment
Variables → `API_PROXY_TARGET` → Edit**. Le redéploiement du **proxy seul**
suffit ; ni le web ni l'admin ne sont rebuildés.

> **Pourquoi `API_PROXY_TARGET` d'abord ?** `resolveBackendBaseUrl()` lui donne
> la priorité sur `BACKEND_API_URL` puis `NEXT_PUBLIC_API_URL`. On peut donc
> basculer d'API **sans toucher** aux variables publiques déjà utilisées pour
> d'autres usages (CSP, liens, `connect-src`). Une seule variable à changer,
> un seul redéploiement.

## Vérification (obligatoire, 3 commandes)

```bash
# 1. Le proxy suit bien la nouvelle stack (compare le commit à celui de l'API)
curl -s https://gestionemployer-backend.vercel.app/api/v1/health

# 2. L'API cible répond directement
curl -s https://leopardo-dev-b.onrender.com/api/v1/health

# 3. Le parcours utilisateur réel : login à travers le proxy
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  -H 'Content-Type: application/json' -H 'Origin: https://leo-admin.pages.dev' \
  -d '{"email":"<admin>","password":"<mot de passe>"}' \
  https://gestionemployer-backend.vercel.app/api/v1/platform/auth/login
```

Les trois doivent renvoyer **200** et la version du `/health` doit correspondre
au commit déployé. Un `503` avec `x-render-routing: suspend-by-user` signifie
que la cible n'a **pas** été changée (ou que le redéploiement du proxy n'a pas
eu lieu).

## Ce qui n'est plus nécessaire

- ❌ Rebuilder le dashboard admin après une bascule — il pointe le proxy.
- ❌ Rebuilder le web pour changer d'API — `API_PROXY_TARGET` est lu au runtime.
- ❌ Changer `NEXT_PUBLIC_API_URL` (sauf si l'on veut aussi changer les
  origines CSP/`connect-src`).

## Garde-fous en place

| Garde | Où | Ce qu'elle empêche |
|---|---|---|
| `assertProductionInvariant()` | `front/admin-dashboard/src/services/api-base-url.js` | un build de prod qui appelle un fournisseur en direct (dérogation : `VITE_ALLOW_DIRECT_API_IN_PROD=true`) |
| `resolveBackendBaseUrl()` fail-fast | `front/web/src/lib/backend-url.ts` | un build/runtime sans URL configurée (aucun repli silencieux) |
| Liste CORS fermée | `front/web/src/app/api/v1/[...path]/route.ts` | un préflight admin sans `Access-Control-Allow-Origin` (appel bloqué par le navigateur) |

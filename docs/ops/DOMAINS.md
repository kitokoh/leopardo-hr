# Registre canonique des domaines — Leopardo RH (source de vérité)

> Source unique de vérité sur les domaines du projet (issue #3706).
> Toute référence first-party (code, config, docs, CI, collections, README)
> doit correspondre à une ligne du tableau « Registre » ci-dessous.
> Garde CI : `dev-hub/tools/check-canonical-domains.sh` (échoue si un domaine
> hors registre apparaît ou si le registre doc/garde désynchronise).
> Vérification DNS/HTTP : **2026-08-15** (audit qa-expert11).

## Domaines actuellement joignables (`live`)

| Surface | Domaine | Usage |
|---|---|---|
| API Laravel | `https://gestionemployerbackend.onrender.com` | API, santé, documentation et clients web/mobile/kiosque |
| API versionnée | `https://gestionemployerbackend.onrender.com/api/v1` | Base URL des consommateurs API |
| Portail web Vercel | `https://gestionemployer-backend.vercel.app` | Vitrine et parcours web actuellement déployés |
| Admin plateforme (super-admin) | `https://leo-admin.pages.dev` | Back-office super-admin (Cloudflare Pages, #3766) |

Ces valeurs correspondent aux defaults exécutables et au backend Render vérifié
joignable (HTTP 200). Elles doivent rester la référence pour les builds tant que
le DNS de production n'est pas provisionné (#3452).

## Domaines de production réservés (`target` — NXDOMAIN au 2026-08-15)

`www.leopardo-rh.com`, `leopardo-rh.com`, `app.leopardo-rh.com`,
`admin.leopardo-rh.com`, `api.leopardo-rh.com`, `docs.leopardo-rh.com`,
`api.leopardo.app`, `proxy.leopardo-rh.com`, `demo.leopardo-rh.com`,
`api-staging.leopardo-rh.com`, `demo.leopardo.app`, `client-a.leopardo-rh.com`
(exemple illustratif tenant), `noreply@leopardo-rh.com` (expéditeur mail).

Ils ne doivent **pas** être utilisés comme defaults de build ni comme URL de
smoke test avant validation DNS/HTTP. La mise en place du DNS et des certificats
reste une responsabilité d'infrastructure distincte (#3452).

## Registre (machine-checkable — miroir de la garde)

| Domaine | Usage | Statut | Note |
|---|---|---|---|
| `gestionemployerbackend.onrender.com` | API backend (base `/api/v1`) — service Render | `live` | Backend effectivement joint par la prod. |
| `gestionemployer-backend.vercel.app` | Vitrine/Web frontend (Vercel) | `live` | HTTP 200 vérifié le 2026-08-15. |
| `leo-admin.pages.dev` | Admin plateforme super-admin (Cloudflare Pages) | `live` | `CORS_ALLOWED_ORIGINS` + `SANCTUM_STATEFUL_DOMAINS` de référence (#3766). |
| `api.leopardo-rh.com` | API backend cible (`APP_URL`) | `target` | NXDOMAIN — #3452. |
| `app.leopardo-rh.com` | Web app cible (`FRONTEND_URL`, CORS, SANCTUM) | `target` | NXDOMAIN — #3452. |
| `leopardo-rh.com` | Vitrine cible | `target` | NXDOMAIN — vitrine DOWN (#3452). |
| `www.leopardo-rh.com` | Vitrine (canonique www) | `target` | NXDOMAIN — #3452. |
| `api.leopardo.app` | API cloud Edge (`CLOUD_API_URL`) | `target` | NXDOMAIN — le cloud Edge joint actuellement le domaine `live`. |
| `proxy.leopardo-rh.com` | Proxy caméras (`CAMERAS_STREAM_BASE_URL`) | `target` | NXDOMAIN — #3452. |
| `admin.leopardo-rh.com` | Back-office admin cible (CORS) | `target` | NXDOMAIN — #3452. |
| `docs.leopardo-rh.com` | Documentation publique cible | `target` | NXDOMAIN — #3452. |
| `demo.leopardo-rh.com` | Environnement démo cible (`dev-hub/demo`) | `target` | NXDOMAIN — #3452. |
| `api-staging.leopardo-rh.com` | API staging cible (`dev-hub/load`) | `target` | NXDOMAIN — #3452. |
| `demo.leopardo.app` | App démo Edge (test SignupForm) | `target` | NXDOMAIN — #3452. |
| `client-a.leopardo-rh.com` | Exemple illustratif tenant (docs MULTITENANCY) | `target` | Usage documentaire uniquement. |

## Règles

1. **Valeur de build** : les defaults (workflows, `backend-url.ts`, `next.config.ts`,
   kiosk `apiBaseUrl`, Postman, scripts smoke) pointent un domaine `live` — ne pas
   basculer sur un `target` tant que #3452 n'est pas résolu.
2. **Config API** : `api/.env.example` documente les domaines joignables en
   commentaire ; chaque environnement de déploiement définit ses valeurs réelles.
3. **Nouveau domaine** : ajouter une ligne au registre (doc + garde) AVANT de
   référencer le domaine dans le code.
4. **Mise à jour** : après modification du DNS, re-vérifier avec
   `getent hosts <domaine>` + `curl -sS -o /dev/null -w "%{http_code}" https://<domaine>`
   et mettre à jour ce document + la date.

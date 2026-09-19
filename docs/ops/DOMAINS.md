# Registre canonique des domaines — Leopardo (source de vérité)

## Domaines actuellement joignables (`live`)

| Surface | Domaine | Usage |
|---|---|---|
| API Laravel | `https://gestionemployerbackend.onrender.com` | API, santé, documentation et clients web/mobile/kiosque |
| API versionnée | `https://gestionemployerbackend.onrender.com/api/v1` | Base URL des consommateurs API |
| Portail web Vercel | `https://gestionemployer-backend.vercel.app` | Vitrine et parcours web actuellement déployés |
| Admin plateforme (super-admin) | `https://leo-admin.pages.dev` | Back-office super-admin (Cloudflare Pages, #3766) |
| Site marketing / product site | `https://kitokoh.github.io/leopardo-hr/` | GitHub Pages (depuis main, #6827) — HTTP 200 vérifié 2026-09-09 |
| API prod (topologie tag) | `https://leopardo-prod.onrender.com` | API prod — déployée uniquement sur tag validé (`deploy-prod.yml`) |
| Portail web prod (topologie tag) | `https://leopardo-prod.vercel.app` | Vitrine/portail web prod — projet Vercel `leopardo-prod` |
| Admin prod (topologie tag) | `https://leo-admin-prod.pages.dev` | Back-office admin prod — projet Cloudflare Pages `leo-admin-prod` (compte prod) |

Ces valeurs correspondent aux defaults exécutables et au backend Render vérifié
joignable (HTTP 200). Elles doivent rester la référence pour les builds tant que
le DNS de production n'est pas provisionné (#3452).

## Domaines de production réservés (`target` — NXDOMAIN au 2026-08-15)

`www.leopardo-rh.com`, `leopardo-rh.com`, `app.leopardo-rh.com`,
`admin.leopardo-rh.com`, `api.leopardo-rh.com`, `docs.leopardo-rh.com`,
`api.leopardo.app`, `proxy.leopardo-rh.com`, `demo.leopardo-rh.com`,
`api-staging.leopardo-rh.com`, `demo.leopardo.app`, `client-a.leopardo-rh.com`
(exemple illustratif tenant), `mail.leopardo-rh.com` (domaine d'expédition
Mailgun PROD), `noreply@leopardo-rh.com` (expéditeur mail).

Ils ne doivent **pas** être utilisés comme defaults de build ni comme URL de
smoke test avant validation DNS/HTTP. La mise en place du DNS et des certificats
reste une responsabilité d'infrastructure distincte (#3452).

## Registre (machine-checkable — miroir de la garde)

| Domaine | Usage | Statut | Note |
|---|---|---|---|
| `gestionemployerbackend.onrender.com` | API backend (base `/api/v1`) — service Render | `live` | Backend effectivement joint par la prod. |
| `gestionemployer-backend.vercel.app` | Vitrine/Web frontend (Vercel) | `live` | HTTP 200 vérifié le 2026-08-15. |
| `leo-admin.pages.dev` | Admin plateforme super-admin (Cloudflare Pages) | `live` | `CORS_ALLOWED_ORIGINS` + `SANCTUM_STATEFUL_DOMAINS` de référence (#3766). |
| `leopardo-prod.onrender.com` | API prod (topologie tag `vX.Y.Z`) — service Render `leopardo-prod` | `live` | Déployé par `deploy-prod.yml` (2026-09-03/04). |
| `leopardo-prod.vercel.app` | Vitrine/portail web prod — projet Vercel `leopardo-prod` | `live` | Déployé par `deploy-prod.yml` (2026-09-04). |
| `leo-admin-prod.pages.dev` | Admin plateforme prod — projet Cloudflare Pages `leo-admin-prod` (compte prod) | `live` | Déployé par `deploy-prod.yml` (2026-09-04). |
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
| `mail.leopardo-rh.com` | Domaine d'expédition Mailgun PROD (garde livraison #6919) | `target` | NXDOMAIN — DNS #3452 ; référence config prod, pas de build. |

## Surfaces verticales — sous-domaines Vercel v1 (issue #6918)

Chaque verticale (Restaurant, Travel, Fuel, Edu, Delivery) reste accessible
depuis la plateforme centrale **et** expose ses surfaces publiques sur son
propre sous-domaine Vercel (décision fondateur 2026-09-06, #6918). Pattern :
**1 verticale = 1 projet Vercel = 1 sous-domaine**.

- `leopardo-<verticale>.vercel.app` — tier dev/continu, compte Vercel `africanovatech` ;
- `leopardo-<verticale>-prod.vercel.app` — tier prod, compte Vercel `ibrahimkoubaye` ;
- Bascule v2 (domaines achetés) : domaine personnalisé `<verticale>.<domaine principal>`
  sur le même projet Vercel → zéro rework (v2, hors périmètre #6918).
- Repli documenté si Vercel bloquait : Cloudflare Pages derrière CNAME (le
  blocage des sous-domaines Pages gratuits est par hostname, pas par compte).
- Les pages servies à ce jour sont des **pages de validation** (HTTP 200,
  vérifié 2026-09-06) ; les vraies applications verticales les remplaceront —
  pilote Restaurant : issue #6920.

| Domaine | Statut | Note |
|---|---|---|
| `leopardo-resto.vercel.app` | `live` | Verticale Restaurant — dev, projet `prj_i8rEi8fCwkkC4TEfAjIXue0fTNKP` ; page de validation, vraie app #6920.  vérifié 2026-09-09. |
| `leopardo-travel.vercel.app` | `live` | Verticale Travel — dev, projet `prj_9zeAXtwVQXgLoVcEurVzJnuVFQZr` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-fuel.vercel.app` | `live` | Verticale Fuel — dev, projet `prj_Tbnb0Grlsdqw3Ce3vkxIrmiKfl4u` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-edu.vercel.app` | `live` | Verticale Edu — dev, projet `prj_fKKFiaiSAMhymCo5BvHW0wD75mb3` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-delivery.vercel.app` | `live` | Verticale Delivery — dev, projet `prj_SUTHkL4joPdi5REZc5chw7ugX86i` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-resto-prod.vercel.app` | `live` | Verticale Restaurant — prod, projet `prj_YMFmR1mJlS1ZjzBQ7Dx8GAzRdgx3` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-travel-prod.vercel.app` | `live` | Verticale Travel — prod, projet `prj_1Y1rr2syuPjMFZWh9EWEK5UOnZOJ` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-fuel-prod.vercel.app` | `live` | Verticale Fuel — prod, projet `prj_qB0D9JbcoFXeGCx1PuA1gydCfPk9` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-edu-prod.vercel.app` | `live` | Verticale Edu — prod, projet `prj_unI8uJqhyGoHXVL8zkXvu4DiY1cS` ; page de validation.  vérifié 2026-09-09. |
| `leopardo-delivery-prod.vercel.app` | `live` | Verticale Delivery — prod, projet `prj_nkmn7sTTn0wtoZ2FS7uY1LaszLyw` ; page de validation.  vérifié 2026-09-09. |

CORS/SANCTUM_STATEFUL_DOMAINS côté API et liens du hub central : à raccorder
via ce registre (pas de hardcode) quand une vraie application verticale utilise
le sous-domaine (suivi #6918/#6920).

## Règles

1. **Valeur de build** : les defaults (workflows, `backend-url.ts`, `next.config.ts`,
   kiosk `apiBaseUrl`, Postman, scripts smoke) pointent un domaine `live` — ne pas
   basculer sur un `target` tant que #3452 n'est pas résolu.
2. **Desktop** : aucun canal public (aucun installateur distribué — #3257). Les apps
   Flutter ont des scaffolds `windows/`/`macos/` mais rien n'est publié ; interdiction
   d'annoncer un domaine/canal desktop dans ce registre tant que le protocole P06 n'a pas
   produit de livrable.
3. **Config API** : `api/.env.example` documente les domaines joignables en
   commentaire ; chaque environnement de déploiement définit ses valeurs réelles.
4. **Nouveau domaine** : ajouter une ligne au registre (doc + garde) AVANT de
   référencer le domaine dans le code.
4. **Mise à jour** : après modification du DNS, re-vérifier avec
   `getent hosts <domaine>` + `curl -sS -o /dev/null -w "%{http_code}" https://<domaine>`
   et mettre à jour ce document + la date.

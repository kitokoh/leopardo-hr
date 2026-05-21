# Client UX Observability - Plan 18

Date : 2026-05-21

## Objectif

Mesurer le parcours client critique `login -> dashboard utilisable` sans dependre d'un fournisseur analytics externe. Le portail web emet des evenements navigateur stables, verifies par Playwright, puis persiste les evenements authentifies via `POST /api/v1/client-events`.

## Evenements contractuels

| Evenement | Surface | Declenchement | Proprietes minimales |
| --- | --- | --- | --- |
| `login_success` | `front/web` | Apres `POST /auth/login` + `GET /auth/me` valides | `duration_ms`, `role`, `manager_role`, `locale`, `target`, `company_id` |
| `login_failed` | `front/web` | Erreur login lisible sans redirection automatique | `duration_ms`, `status`, `code` |
| `dashboard_loaded` | `front/web` | Dashboard manager/employe/super-admin utilisable | `duration_ms`, `surface`, `role`, `company_id`, `active_modules`, `locked_modules` |
| `feature_blocked` | `front/web` | Module masque par plan ou role | `module`, `reason`, `state` |
| `demo_user_selected` | `front/web` | Selection d'un compte demo | `role`, `country`, `email_domain` |
| `leopardo:kiosk-status` | `front/zkteco-kiosk` | Rafraichissement etat bridge local | `online`, `queue_count`, `device_code`, `last_sync_at` |

## Persistance API

- Endpoint : `POST /api/v1/client-events`
- Authentification : Sanctum employee + middleware `tenant` + `api-plan`
- Rate limit dedie : `client-analytics` (`RATE_LIMIT_CLIENT_ANALYTICS_PER_MINUTE`, defaut 120/min/company)
- Stockage : table tenant `client_events`
- Evenements acceptes cote backend : `login_success`, `dashboard_loaded`, `feature_blocked`, `demo_user_selected`, `kiosk_status`
- Evenement volontairement non persiste : `login_failed`, car il peut etre anonyme et ne dispose pas toujours d un tenant fiable. Il reste observable localement et dans Playwright.
- Minimisation : l API garde uniquement une allowlist de proprietes scalaires et rejette les champs PII accidentels comme email complet ou payload imbrique.

## Seuils UX

| Parcours | Seuil bloquant CI | Objectif production p75 | Notes |
| --- | ---: | ---: | --- |
| Login API + `auth/me` + redirection dashboard mockee | `< 5000 ms` | `< 2500 ms` | Mesure Playwright via `login_success` puis `dashboard_loaded`. |
| Dashboard manager mocke utilisable | `< 5000 ms` | `< 2000 ms` | Les endpoints dashboard reels doivent etre caches/optimises si p75 depasse le seuil. |
| Login page Lighthouse LCP | `< 2500 ms` | `< 2000 ms` | Ajoute dans `front/web/lighthouserc.json`. |
| Login/dashboard CLS | `< 0.10` | `< 0.05` | Aucune carte dynamique ne doit pousser brutalement le contenu principal. |
| Interaction principale INP | `< 200 ms` | `< 150 ms` | A suivre via Web Vitals quand l endpoint analytics sera branche. |

## Gardes automatiques

- `front/web/e2e/auth-client-smoke.spec.ts` verifie `login_success`, `login_failed`, `dashboard_loaded` et `demo_user_selected`.
- `front/web/e2e/client-feature-gates.spec.ts` verifie `feature_blocked` pour plan bloque et role bloque.
- `front/web/e2e/client-visual-smoke.spec.ts` attache des captures Playwright de la page login et du dashboard dans le rapport CI.
- `.github/workflows/web-marketing-ci.yml` publie le rapport Playwright `web-client-playwright-report`.

## Prochaine evolution

Prochaine evolution : ajouter un endpoint de lecture agrege pour le dashboard plateforme, puis exporter les agregats vers CRM/data warehouse sans exposer les evenements individuels aux utilisateurs tenant.

# Stack — front/admin-dashboard (console super-admin plateforme)

> Document de référence exigé par l'issue #7849 (audit externe 2026-09-20 :
> stack non documentée, l'auditeur croyait ce projet en React/TS).
> **Réalité : Vue 3 + JavaScript pur (pas de TypeScript).**
> Décision de typage : voir l'ADR
> [`docs/architecture/adr/0023-admin-dashboard-typage.md`](../../docs/architecture/adr/0023-admin-dashboard-typage.md).

## Vue d'ensemble

| Aspect | Valeur |
|---|---|
| Rôle | Cockpit **super-admin plateforme** (tenants, RBAC plateforme, billing/subscriptions, système) — cf. `docs/architecture/FRONT_DASHBOARDS_DECISION.md` |
| Langage | **JavaScript (ESM, `type: module`)** — aucun `tsconfig.json`, zéro `.ts` |
| Framework | **Vue 3.5** (`vue@^3.5.42`), Composition API, `<script setup>` |
| État | **Pinia** (`pinia@^4.0.3`) — stores en *setup syntax* (`src/stores/`) |
| Routing | **vue-router** (`^5.3.1`), history mode, guards d'auth/permissions dans `src/router/index.js` |
| Build | **Vite 8** (`vite@^8.3.0`) + `@vitejs/plugin-vue` ; alias `@` → `src/` |
| CSS | **Tailwind CSS 4** (`@tailwindcss/postcss`) + Headless UI + Heroicons |
| HTTP | **axios** (`^1.20.0`) via client central `src/services/api.js` (intercepteurs, breadcrumbs, messages localisés) |
| Temps réel | **socket.io-client** (`src/stores/realtime.js`) |
| Divers | chart.js/vue-chartjs, echarts, leaflet/vue3-leaflet, globe.gl + three, date-fns, nprogress, vue-toastification |
| i18n | Maison, `src/i18n/` (fr/en), locale via store `locale.js` |
| Déploiement | SPA statique **Cloudflare Pages** (`leo-admin.pages.dev`), CSP générée au build (`scripts/csp.mjs` + guard `scripts/check-csp-guard.mjs`, fail-closed #7695) |

## Volume (mesuré au 2026-09-20, branche `main`)

- **96 fichiers `.vue`** + **18 fichiers `.js`** sous `src/` — **≈ 32 800 LOC** au total.
- `.js` : ≈ 3 520 LOC, dont les plus gros : `navigation/navigation.js` (695),
  `router/index.js` (629), `services/api.js` (470), `stores/realtime.js` (427),
  `stores/auth.js` (314).
- Vues par domaine : `views/{accounting,analytics,auth,chat,communication,companies,crm,edge,exports,fleet,fuel,globe,growth,marketing,settings,showcase,solutions,subscriptions,support,system,team,training,travel,users,webhooks}`.

## Surfaces sensibles (sans typage aujourd'hui)

| Surface | Fichiers | Pourquoi c'est critique |
|---|---|---|
| Auth plateforme | `src/stores/auth.js`, `src/services/token-storage.js` | Token super-admin en mémoire volatile (#1299/#7695) ; permissions effectives (#7553/#7557) |
| Couche API | `src/services/api.js` (+ `travel.js`, `showcase.js`) | Tout le trafic vers l'API passe par là (intercepteurs 401, erreurs localisées) |
| RBAC / guards | `src/router/index.js` (`meta.requiresAuth`, permissions) | Contrôle d'accès aux vues plateforme |
| Billing | `src/views/subscriptions/`, `src/views/accounting/` | Manipulation de plans/factures tenants |

## Qualité & tests

- **Lint** : ESLint 10 **flat config** (`eslint.config.js`) — `@eslint/js` recommended
  + `eslint-plugin-vue` (flat/essential) + `@babel/eslint-parser`.
  `no-undef: error` (réactivé par #2481), règles anti-eval/`v-html` en `error`.
- **Format** : Prettier 3 (`npm run format`).
- **Tests** : **aucun test unitaire** (pas de Vitest/Jest).
  **33 specs Playwright e2e** (`e2e/`) + 3 specs staging (`e2e-staging/`) :
  smoke auth, navigation, flux métier (paie, congés, exports, travel…).
- **Typage** : `jsconfig.json` présent (depuis #7849) en mode **non-bloquant**
  (`checkJs: false`) — sert l'IntelliSense éditeur (alias `@/`, libs DOM).
  Aucun fichier converti ; le plan incrémental est dans l'ADR 0023.

## Commandes

```bash
cd front/admin-dashboard
npm ci                    # Node ≥ 20 recommandé (CI : cf. web-ci.yml)
npm run dev               # Vite dev server, port 3001, proxy /api → localhost:8000
npm run lint              # ESLint flat config
npm run build             # vite build + guard CSP (scripts/check-csp-guard.mjs)
                          # ⚠ en production, VITE_API_URL est OBLIGATOIRE (#4715, fail-closed)
npm run preview
npm run test:e2e          # Playwright (voir playwright.config.js)
npm run test:e2e:staging
```

## Conventions

- Composition API + `<script setup>` partout ; stores Pinia en setup syntax.
- Client HTTP : **toujours** via `src/services/api.js` (jamais d'axios direct).
- Token : **uniquement** via `src/services/token-storage.js` — ne pas
  réintroduire `localStorage`/`sessionStorage` (#1575, #7695).
- Toute dépendance ajoutée exige une décision documentée (politique repo).
- Nouveaux fichiers : viser du JSDoc typé sur services/stores (cf. ADR 0023,
  phase 1) en attendant l'outillage `checkJs`/`vue-tsc`.

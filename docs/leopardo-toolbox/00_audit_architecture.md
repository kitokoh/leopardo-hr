# Audit de l'architecture Leopardo — Phase 0 (LEOPARDO TOOLBOX)

> **Date** : 2026-11-05 — commit audité : `dd7522d74f8fc91f6b16a4cdfe4d87909a077cba` (main, HEAD)
> **Méthode** : clonage réel du dépôt `kitokoh/leopardo-hr` (public), inspection des fichiers. Aucune hypothèse ; tout constat est sourcé par un fichier/dossier vérifié à la date ci-dessus.

---

## 1. Vue d'ensemble du monorepo

| Entrée | Réalité constatée |
|---|---|
| `api/` | Backend Laravel (composer.json, PHP 8.4+), **39 modules DDD** sous `api/app/Modules/` (vérifié `ls | wc -l` = 39), socle transversal `api/app/Core/` (Auth, Tenant, Feature, AI, Http, Notifications, Privacy, Seed, Solutions), `api/app/Shared/` (DTOs, Traits, Attributes, Enums, Exceptions). Routes : `routes/api.php` (87 Ko), `routes/modules/`, `routes/web.php`, `console.php`. OpenAPI canonique `api/openapi.yaml` (2,3 Mo), miroir `dev-hub/openapi/v1.yaml`. |
| `front/` | 6 familles : `web` (Next.js 16, vitrine+dashboard), `web-offline` (PWA), `admin-dashboard` (Vue 3), `marketplace`, `travel-web`, `mobile_apps` (9 apps Flutter, melos), `zkteco-kiosk`. |
| `shared/` | `i18n/` (locales fr/en/ar/tr JSON, sync vers web/backend/mobile), `mediaForMarketing/`. |
| `dev-hub/` | Outils développeur, miroir OpenAPI, registres de gouvernance (`governance/bounded-context-registry.json`), outils de garde CI. |
| `docs/` | Architecture, ADR (`docs/adr/`), décisions datées (`docs/DECISIONS/`), missions (business-os, leopardo), gouvernance (`docs/GOUVERNANCE/`), security, etc. |
| `.github/workflows/` | 72 pipelines CI/CD (README de cartographie), dont `web-marketing-ci.yml` (vitrine), `tests.yml`, `deploy-main.yml`, `vitrine-lock-guard.yml`. |
| Déploiement | Render (`render*.yaml`), Vercel pour les fronts Next.js, GitHub Pages (`site/gh-pages/` + `pages-deploy.yml`). |

## 2. Vitrine publique (front/web) — détail

### 2.1 Stack (package.json vérifié)
Next.js **16.4.3**, React 19.3.1, TypeScript 5.9.3, Tailwind 4 (postcss), framer-motion 14, gsap, lucide-react, zod 4, react-hook-form. Scripts : `lint` (ESLint `--max-warnings 0`), `typecheck` (tsc --noEmit), `test` (Jest 30, jsdom, Testing Library), `test:e2e` (Playwright), gardes `check:mojibake`, `check:assets`, `check:orphans`, `check:french-copy`, `build`.

### 2.2 Routes (App Router)
- `src/app/(landing)/` : vitrine marketing — `about`, `alternatives/[slug]`, `blog/[slug]`, `branding`, `careers`, `case-studies/[slug]`, `changelog`, `checkout(/)`, `comptabilite`, `contact`, `demo`, `docs`, `documents`, `download`, `employes`, `faq`, `guides/*`, `integrations`, `investir`, `marketing`, `mobile`, `packs`, `pricing`, `restaurateur`, `testimonials`, `videos`, …
- `src/app/(dashboard)/` : zone connectée (RH, paie, comptabilité, CRM, santé, etc.) — protégée.
- Routes publiques racine : `auth/*`, `boutiques`, `cagnottes`, `conversations`, `decouvrir` (**passerelle publique / Leopardo Public**, #8611), `documents`, `entreprises`, `guardian-portal`, `kiosk`, `mentions-legales`, `offline`, `operateurs`, `order`, `privacy`, `restaurants`, `share`, `shop`, `status`, `stay`, `terms`, `travel`, `view`, `vitrine/[slug]`.
- Middleware : `src/proxy.ts` (Next 16) — gate de session cosmétique via cookie `leopardo_token` sur `DASHBOARD_PREFIXES` ; pas d'impact sur les routes hors liste.
- `src/lib/protected-prefixes.ts` : source unique des préfixes protégés (test de régression), incluant `PROTECTED_PREFIXES` et `VITRINE_LANG_PREFIXES`.

### 2.3 Navigation & i18n vitrine
- `src/modules/vitrine/components/Navbar.tsx` : **≤ 7 entrées** (#8387) — `pricing`, dropdown `packs`, `alternatives`, `careers`. Libellés depuis `t(locale, 'vitrine.nav.*')` (catalogue partagé).
- `src/modules/vitrine/components/Footer.tsx` : liens par `getFooterHref(sectionIndex, linkIndex)` (route map), badges, copy typée.
- `src/modules/vitrine/lib/vitrine-locale.ts` : `VitrineCopy`/`LandingCopy` (nav, hero, footer, …) **en dur dans 4 blocs FR/EN/TR/AR** (#7665 partiel — nav/footer via catalogue, mais une grande partie de la copy vitrine reste dans ce fichier).
- `src/lib/i18n/locale-catalog.ts` : charge `locales/{fr,ar,tr,en}.json` générés par `shared/i18n/sync/sync-web.js` (source `shared/i18n/locales/*.json`).
- `src/modules/vitrine/lib/locale-href.ts` + middleware : préservation de `?lang=` et normalisation `x-vitrine-lang`.

### 2.4 Module vitrine (module frontend autonome)
`src/modules/vitrine/` = **zone verrouillée** (AGENTS.md du module + garde CI) : composants (layout, sections, common, forms, hero, guides), `data/` (catalogues statiques : pricing, features, solutions, blog, etc.), `lib/` (seo, i18n, funnel, forms, validation), `hooks/`, `types/`, tests. Design system : palette emerald/or, dark mode, halos, composants `common/*` (Button, Card, Input, Badge, Container, Section).

### 2.5 SEO & indexation
- `src/app/sitemap.ts` : liste statique (≈ 30 routes) + alternates `?lang=` 4 langues + x-default ; `revalidate = 3600`.
- `src/app/robots.ts` : disallow basé sur `PROTECTED_PREFIXES` ; tout le reste est indexable.
- Pages publiques existantes servant de référence : `/decouvrir` (SSR indexable, métadonnées SEO par `generateMetadata`), `/restaurants`.

### 2.6 Tests vitrine
Jest (jsdom) sous `src/**/__tests__/` + intégration `modules/vitrine/__tests__/integration/*` (conversion, landing, navigation-routing), Playwright e2e dans `front/web/e2e/`. CI `web-marketing-ci.yml` : lint, mojibake, assets, orphelins, accents FR, build (avec `NEXT_PUBLIC_API_URL` + `NEXT_PUBLIC_SITE_URL`), funnel e2e.

## 3. Authentification, permissions, tenants

| Sujet | Constat |
|---|---|
| Session front | Cookie `leopardo_token` ; gate cosmétique `proxy.ts` (réelle sécurité côté API). |
| Auth API | `api/app/Core/Auth`, middlewares (TenantMiddleware, etc.), oauth Google, magic link, password policy. |
| RBAC | Rôles : `super_admin`, `company_admin`, `hr_manager`, `manager`, `employee` ; Policies Laravel obligatoires par action (docs/security/RBAC_ROUTE_MATRIX.md). |
| Multi-tenant | PostgreSQL `search_path` via `Core/Tenant/TenantManager` ; trait `BelongsToCompany` ; migrations tenant vs public (règle #1613, pattern `resolveTableSchema`). |
| Feature flags | `Core/Feature` ; côté front `src/lib/client-features.ts` (`CLIENT_MODULES`, ~40 modules, groupes nav `hr/finance/growth/operations/platform`). |

## 4. Gouvernance qui s'applique à cette mission (vérifié)

1. **Verrou vitrine** (`vitrine-lock-guard.yml` + `check-vitrine-agent-lock.sh`) : toute PR touchant `front/web/src/modules/vitrine/**` ou `front/web/src/app/(landing)/**` doit venir d'une branche `(feat|fix|chore|mkt|agent)/vitrine-*`, de l'auteur `kitokoh`, ou porter le label `vitrine-authorized`. → **contrainte directe** pour l'intégration navbar/footer.
2. **Freeze Scope 60j** (#5147) : **expiré** (gate J60 `held` au 2026-10-26, pas de reconduction) — n'est plus une règle de revue ; conservé comme contexte.
3. **Constitution** (`.specify/constitution.md`) : spec d'abord ; pas de nouvelle table tenant sans `company_id` ; multi-tenant inviolable ; DDD obligatoire pour tout code API ; PHPStan level max bloquant.
4. **Spec-First** : toute feature significative = spec + issue GitHub avant implémentation.
5. **i18n** : 4 langues, clés dans le catalogue partagé, garde `check-hardcoded-accented-messages.sh` (backend) et `check:french-copy` (front) interdisant les littéraux accentués hors i18n.
6. **Conventions** (`CONVENTIONS.md` + `AGENTS.md`) : modules DDD, routes kebab-case pluriel, POST pour actions, migration `search_path`, branches par issue (`fix/<issue>-<slug>` ; lots BC = `bc/<code>-<slug>`), une PR par issue.

## 5. Existant pertinent pour Toolbox

| Élément | Fichier(s) | Intérêt |
|---|---|---|
| Calculateur client-side | `src/modules/vitrine/components/sections/RoiCalculator.tsx` | Précédent de « simulateur » 100 % client dans la vitrine |
| Passerelle publique | `src/app/decouvrir/*` + `src/lib/public-discovery-api.ts` | Précédent de surface publique sans auth, indexable |
| Composants UI vitrine | `src/modules/vitrine/components/common/*` | Réutilisables (Button, Card, Input…) |
| i18n web | `src/lib/i18n/locale-catalog.ts`, `shared/i18n/locales/*.json`, `sync-web.js` | Chaîne de traduction à étendre |
| Catalogue frontend | `src/modules/vitrine/data/*.ts`, `src/lib/client-features.ts` | Pattern « catalogue déclaratif » (métadonnées séparées) déjà utilisé |
| Sitemap | `src/app/sitemap.ts` | À étendre pour indexer `/outils` |
| Garde orphelins | `front/web/scripts/check-orphan-pages.mjs` | Toute nouvelle page (landing) doit être liée (navbar/footer) |
| RBAC/perms | `api/app/Modules/*/Domain/Policies/`, `Shared/Attributes/RequiresPermission` | Non touché au périmètre 1 (aucun endpoint) |

## 6. Risques & points d'attention relevés

1. **Verrou vitrine** : ne pas tenter une PR touchant `modules/vitrine/**` ou `(landing)/**` hors branche `vitrine-*` — elle serait bloquée en CI.
2. **Copy vitrine en dur** (vitrine-locale.ts) : pour le libellé « Outils » en navbar/footer, privilégier le catalogue partagé `vitrine.nav.*` (pattern Navbar) plutôt que d'ajouter du FR en dur (gardes accents/mojibake).
3. **Garde orphelins #8075** : `/outils` devra être lié depuis navbar ou footer (ou le check échouera si placé dans `(landing)` ; hors `(landing)` la garde ne s'applique pas, mais l'intégration vitrine demande la cohérence).
4. **Pas de nouvel endpoint API** : recommandé (calculs client) ; toute exception (persistance « Mes outils ») nécessite décision + module DDD + migrations tenant + tests cross-tenant.
5. **Taux (devises, salaire net)** : sources officielles documentées, date de référence, avertissement « estimation » — jamais présenté comme conseil/validation.
6. **Node 20 en CI** (web-marketing-ci `node-version: 20.x`) : le code doit compiler sous Node 20 (local vérifié : Node v20.20.2).

## 7. Conclusion

Le dépôt est un monorepo mature avec des conventions strictes (DDD, i18n 4 langues, multi-tenant, gardes CI). La Toolbox s'y insère **proprement en frontend Next.js autonome** (`src/modules/toolbox/`), routes publiques `/outils*`, catalogue déclaratif, calculs purs testés — sans toucher au backend, à l'identité, aux permissions ni aux paiements. Le verrou vitrine impose une branche préfixée `vitrine-` pour l'intégration navbar/footer/footer-links.

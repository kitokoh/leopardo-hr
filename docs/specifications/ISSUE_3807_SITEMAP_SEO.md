# Mini-spec — Issue #3807

## Problème

L'audit 360° 2026-08-15 (expert QA) a relevé des signaux SEO contradictoires et des canonicals invalides sur la vitrine `front/web` :

1. `sitemap.ts` publiait `/signup` et `/checkout` alors que `pageMetadata.signup/checkout.robots = "noindex, follow"` — signaux contradictoires pour les crawlers.
2. Les études de cas individuelles `/case-studies/{slug}` (pages indexables) étaient absentes du sitemap malgré `getAllCaseStudySlugs()`.
3. `sitemap.ts:36` calculait `lastModified = new Date()` par requête → churn quotidien des lastmod sans changement de contenu.
4. `layout.tsx:43` posait `openGraph.locale: 'fr_FR'` en dur au niveau racine → deep-merge sur toutes les pages : les pages en/tr/ar annonçaient `og:locale=fr_FR`.
5. `privacy/page.tsx` et `terms/page.tsx` utilisaient des canonicals RELATIFS (`/privacy`, `/terms`) — invalides selon Next.js (URL absolue exigée).
6. `/checkout/success` héritait du canonical `/checkout` du layout parent.
7. Régression test : `navbar-locale-url.test.ts` (#3785) et `footer-links.test.ts` (#3786) importent `vitest` alors que le runtime jest (`next/jest`) est utilisé → `npm test` échoue à collecter.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `/signup` et `/checkout` dans le sitemap | Absents |
| `/case-studies/{slug}` dans le sitemap | Présents (1 par slug) |
| `lastModified` des pages statiques | Absent (stable) |
| Canonical privacy/terms/checkout-success | URL absolue correcte |
| `og:locale` | Suit la locale SSR (fr_FR/en_US/tr_TR/ar_AR), plus jamais fr_FR en dur |
| `npm test` (jest) | 22 suites / 358 tests verts |
| `npm run lint`, `tsc --noEmit`, `npm run build` | 0 erreur |

## Correctif

- `sitemap.ts` : retrait des pages noindex, ajout des slugs case-studies, suppression des lastmod volatils.
- `layout.tsx` : `generateMetadata()` dynamique avec `ogLocale()` par locale SSR (cache React par requête, `html lang` inchangé).
- `seo.ts` : option `locale` sur `SEOMetadata` + helper `ogLocaleFor()` ; branché sur `pricing/layout.tsx`.
- `privacy/page.tsx`, `terms/page.tsx` : canonical absolu.
- `checkout/success/layout.tsx` : nouveau layout avec canonical propre + noindex conservé.
- Tests : `src/app/__tests__/sitemap.test.ts` (4 cas), extension `seo.test.ts` (3 cas) ; conversion des imports `vitest` → globals jest dans les 2 tests hérités.

## Validation

`npm test` (358 verts), `npm run lint`, `npx tsc --noEmit`, `npm run build` — tous verts localement ; CI `Web Marketing Lint/Build` + `Frontend — ESLint + TypeScript` en garde.

Closes #3807

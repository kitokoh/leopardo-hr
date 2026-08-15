# Mini-spec — Issue #3808

## Problème

L'audit 360° 2026-08-15 (expert QA) a relevé ~700 lignes de modules morts dans la
vitrine `front/web` — zéro import dans `src/` (hors barrels qui ne sont eux-mêmes
jamais consommés) :

- `components/animations/` : `AnimatedCounter.tsx`, `GradientOrbs.tsx`,
  `ScrollAnimations.tsx` + barrel (HeroSection définit son propre compteur local).
- `hooks/` : `useScrollAnimation.ts`, `useFormSubmit.ts`,
  `useIntersectionObserver.ts` (consommateur unique = `AnimatedCounter` mort).
- `lib/animations.ts` (285 l.), `lib/utils.ts` (354 l. : `cn`, `formatNumber`, …).
- `lib/seo.ts` : helpers `canonicalUrl`, `generateSitemapXML`, `generateRobotsTxt`,
  `getOGImageUrl`, `getCanonicalUrl` — 0 référence externe (sitemap.ts/robots.ts
  sont des route handlers autonomes).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `rg` de chaque symbole supprimé dans `src/` | 0 occurrence hors définition |
| `npx tsc --noEmit` | 0 erreur |
| `npm run lint` | 0 warning |
| `npm test` (jest) | 21 suites / 351 tests verts |
| `npm run build` | vert |

## Correctif

Suppression des fichiers morts et des re-exports correspondants dans les barrels
(`components/index.ts`, `hooks/index.ts`, `lib/index.ts`). Aucun comportement
affecté : aucune UI ne référençait ces modules.

## Validation

`rg` négatif sur chaque symbole, tsc/lint/jest/build verts ; CI
`Web Marketing Lint/Build` + `Frontend — ESLint + TypeScript` en garde.

Closes #3808

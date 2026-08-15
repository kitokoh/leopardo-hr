# Feature Specification: Métadonnées SEO localisées de la vitrine (issue #4004)

**Feature Branch**: `fix/4004-seo-metadata-i18n`
**Created**: 2026-08-15
**Status**: Implemented — validation locale complète (build, lint, tsc, Playwright, curl multi-locales)

## Problème

Les 27 blocs `pageMetadata` de `front/web/src/modules/vitrine/lib/seo.ts` étaient
100 % FR en dur : les visiteurs EN/TR/AR (locale `?lang=`) recevaient un
`<title>` et une meta description français sur toutes les pages de la vitrine.
`generateMetadata()` passait la chaîne telle quelle (pas de localisation).

## Solution

1. **Dictionnaire localisé** `pageMetadataLocalized` (EN/TR/AR, 27 clés × 3
   locales = 81 paires title+description) + helper `localizedPageMetadata(key, lang)`
   avec fallback FR dans `seo.ts`. `resolveSsrLang()` partagé (extrait de
   pricing/root layout, dé-dupliqué).
2. **Middleware** : les landing paths (matcher étendu) transmettent `?lang=` en
   en-tête `x-lang` (les layouts Next ne reçoivent pas `searchParams`) ; fallback
   Accept-Language SSR.
3. **28 layouts** : `export const metadata` → `export async function generateMetadata()`
   lisant `x-lang`/Accept-Language et localisant title+description (keywords/ogImage
   partagés FR). case-studies (par slug) et pricing (SSR) alignés.

## User Stories & Testing

### US1 — Title localisé par ?lang (P1)
**Acceptance Scenarios**:
1. Given `/demo?lang=en`, When GET, Then `<title>` = « Request a Leopardo HR Demo ».
2. Given `/demo?lang=ar`, When GET, Then `<title>` = titre arabe (Unicode propre, check-mojibake OK).
3. Given `/guides/rh-startup` sans ?lang, When GET, Then `<title>` FR (défaut).

### US2 — Pas de régression navigation (P1)
**Acceptance Scenarios**:
1. Given `/guides/rh-startup`, When GET, Then HTTP 200 (pas de redirect auth — régression middleware corrigée et testée).
2. Given le matcher middleware, When `/blog`, `/docs`, `/checkout/*`, Then les paths dynamiques `:path*` matchent (base normalisée).
3. Given la suite e2e vitrine, When run, Then conversion-funnel + client-feature-gates + marketing-funnel verts.

### US3 — Canonique/alternates intacts (P2)
**Acceptance Scenarios**:
1. Given `/demo?lang=en`, When GET, Then `rel=canonical` inchangé, alternates `?lang=` conservés.

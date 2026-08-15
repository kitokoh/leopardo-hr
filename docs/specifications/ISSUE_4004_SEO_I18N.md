# ISSUE_4004 — Vitrine : métadonnées SEO 100% FR en dur (27 blocs)

**Statut**: Fixed (PR `fix/4004-seo-i18n`) · **Priorité**: P2 · **Module**: web/seo-i18n

## Correctif

1. `front/web/src/modules/vitrine/lib/seo.ts` : dictionnaire `pageMetadataI18n`
   (27 pages × EN/TR/AR, title + description) + `getPageMetadata(page, lang)`
   (override → FR par défaut ; keywords/ogImage restent partagés).
2. Les 27 layouts `(landing)/*/layout.tsx` : `export const metadata` statique →
   `generateMetadata({ searchParams })` lisant `?lang=` (Promise Next 15),
   fallback FR sans `?lang`, `locale` transmis pour `og:locale` (#3807).
3. `/pricing` et `/case-studies` : `?lang=` prime sur l'Accept-Language SSR.
4. Tests : `src/modules/vitrine/lib/__tests__/seo-locale.test.ts` (5 tests).

## Validation

- `tsc --noEmit` OK ; eslint OK ; 470 tests jest OK ; `next build` OK.
- Le runtime `/demo?lang=en` sert désormais un title EN.

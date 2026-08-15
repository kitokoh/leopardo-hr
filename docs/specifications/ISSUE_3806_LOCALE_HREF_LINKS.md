# Mini-spec — Issue #3806

## Intention
Préserver la locale active (`?lang=`) sur les liens internes Navbar/Footer/CTA afin
qu'un partage ou refresh d'URL ne ramène pas en FR (la locale ne survit aujourd'hui
qu'au localStorage après #3735, qui n'a corrigé que le `<select>`).

## Contrat attendu

| # | Règle |
|---|-------|
| 1 | Locale active via `?lang=` → clic sur lien interne → l'URL cible conserve `?lang=` |
| 2 | Locale via localStorage sans `?lang=` → lien inchangé (aucun `?lang=` forcé) |
| 3 | Query string existant conservé (`/contact?topic=community&lang=tr`) |
| 4 | Ancres préservées (`/#fonctionnalites` → `/?lang=en#fonctionnalites`) |
| 5 | Liens externes (`https://…`, `mailto:`) jamais modifiés |

## Correctif
- `Navbar.tsx` : nouveau helper exporté `withLocaleHref(href, locale, langActive)`
  (split path/query/hash, `URLSearchParams.set('lang')`, reconstruction).
- `vitrine-locale.ts` : helper `hasLocaleInUrl()` (true si `?lang=`/`?locale=` présent).
- Application aux liens Navbar (desktop + mobile + dropdown), Footer
  (`getFooterHref`), `HeroSection` et `CTASection` (CTA internes).
- Tests unitaires : `front/web/src/modules/vitrine/components/__tests__/locale-href.test.ts`
  (6 cas couvrant le contrat ci-dessus).

## Validation
`npm run lint`, `tsc --noEmit`, `jest` (352 tests verts) — hors 2 suites vitest
préexistantes couvertes par #3802.

# Mini-spec — Issue #3806

## Problème

L'audit 360° 2026-08-15 (expert QA) a constaté que la locale vitrine portée par
`?lang=` dans l'URL ne survit pas à la navigation interne : les liens Navbar
(`href={entry.href}`, Navbar.tsx) et Footer (`getFooterHref`, Footer.tsx) sont
rendus sans `?lang=`.

- Depuis `/?lang=en`, cliquer « Pricing » dans la Navbar → `/pricing` (locale
  perdue pour un partage/refresh d'URL ; la locale ne tient que via localStorage).
- #3735 n'a corrigé que le `<select>` de langue (Navbar), pas les liens.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Clic lien interne depuis une URL avec `?lang=` | L'URL cible conserve `?lang=` |
| Lien externe / ancre / mailto / tel | Inchangé |
| Aucun `?lang=` dans l'URL courante | Liens inchangés (jamais de `?lang=` imposé) |
| Query params + ancre de la cible (`/contact?topic=x#y`) | Conservés |
| Tests unitaires `locale-href.test.ts` | 6 cas verts |
| `npm test`, `npm run lint`, `tsc --noEmit` | 0 erreur |

## Correctif

Nouveau helper `withLocaleHref(href, search)` (`modules/vitrine/lib/locale-href.ts`)
appliqué à tous les liens internes de la Navbar (desktop, dropdowns, drawer mobile)
et du Footer : si la recherche courante porte `lang`, il est fusionné dans la
cible en préservant query params et ancre ; sinon le href est retourné tel quel.

## Validation

Tests `locale-href.test.ts` (6 cas), `npm test` complet (358+ tests), lint et
`tsc --noEmit` verts ; CI `Web Marketing Lint/Build` + `Frontend — ESLint + TypeScript`.

Closes #3806

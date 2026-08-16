# Tasks — Vitrine metadata localisées (issue #4393)

Dépendance : `front/web` (Next.js 15).

## T1 — Helper partagé de résolution SSR

- [x] Ajouter `resolveSsrVitrineLang(urlLang, acceptLanguage)` dans
      `src/lib/i18n.ts` (s'appuie sur `isSupportedLocale` + `normalizeLocale`
      existants).
- [ ] Tests unitaires `src/lib/__tests__/i18n-ssr-lang.test.ts`.

## T2 — Middleware

- [x] `src/middleware.ts` : poser `x-vitrine-lang` sur TOUTES les requêtes
      (défaut fr), `?lang=` prioritaire sinon `resolveSsrVitrineLang(null,
      accept-language)`.
- [x] Supprimer la constante `SUPPORTED_LOCALES` locale devenue inutile.

## T3 — Spec-kit & changelog

- [x] `.specify/features/4393-vitrine-metadata-locale/spec.md` + `tasks.md`.
- [x] Entrée `CHANGELOG.md` sous `## [Unreleased]`.

## T4 — Validation

- [ ] `npx tsc --noEmit` → 0 erreur.
- [ ] `npm run lint` (eslint, max-warnings 0) → 0 warning.
- [ ] `npm test` → vert (dont `i18n-ssr-lang.test.ts`).

## T5 — Livraison

- [ ] Push `fix/4393-vitrine-metadata-locale` + PR avec `Closes #4393`.
- [ ] CI verte (Web CI - Leopardo Vitrine) puis merge.

# Tasks: Métadonnées SEO localisées

- [x] [T1] [P] [US1] Dictionnaire `pageMetadataLocalized` (27×3) + `localizedPageMetadata()` + `resolveSsrLang()` dans `seo.ts`.
- [x] [T2] [P] [US1] Middleware : matcher landing étendu + en-tête `x-lang` depuis `?lang`.
- [x] [T3] [P] [US2] 26 layouts statiques → `generateMetadata()` (headers x-lang/Accept-Language).
- [x] [T4] [P] [US2] case-studies (listing + fallback slug) et pricing (SSR) alignés ; blog/[slug] fallback localisé.
- [x] [T5] [P] [US1+US2] Validation : `tsc --noEmit`, `eslint --max-warnings 0`, `check-mojibake`, `npm run build`, curl 4 locales, Playwright (3 specs).

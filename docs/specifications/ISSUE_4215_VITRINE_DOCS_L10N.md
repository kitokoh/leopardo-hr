# ISSUE 4215 — Page /docs de la vitrine localisée ×4 locales

> Spec rédigée selon la méthodologie spec-kit (`.github/skills/speckit-*`).

## Contexte

`front/web/src/app/(landing)/docs/page.tsx` (538 lignes) était la dernière page
vitrine 100 % FR codé en dur : badge hero, headline, sous-titre, placeholder de
recherche, pills rapides, 9 catégories (titre + 4 items titre/desc chacun),
section API Quick Start, webhooks (4 groupes), SDK Flutter (3 apps), kiosque
(installation/fonctionnement), sécurité/RGPD (4 items), installation mobile
(3 apps), « aucun résultat », liens rapides (3). Aucun `useVitrineLocale`,
aucun `Record<AppLocale>`, page indexée dans le sitemap (0.7) → soft-duplicates
SEO et UX EN/TR/AR cassée. #3248 ne couvrait pas `/docs`.

## Décision

Pattern #3248 tranche guides/videos (merged) :
1. `src/modules/vitrine/data/docs.ts` — `docsPageCopy: Record<AppLocale, DocsContent>`
   (fr/en/tr/ar) : seules les chaînes ; les icônes/couleurs restent dans la page.
2. Page : `useVitrineLocale()` + `docsPageCopy[locale] ?? docsPageCopy.fr` ;
   catégories typées `DocsCategoryId` mappées sur icônes/couleurs locales.
3. Les hrefs d'ancres (`#api-quickstart`, `#kiosk`, `#security`…) et les
   libellés techniques (`Login`, `Bearer token`, `Webhook payload`) restent
   identiques entre locales (documentation technique).

## Critères d'acceptation

1. `/docs` rendu EN/TR/AR sans chaîne FR résiduelle (hors libellés techniques).
2. `tsc --noEmit` + ESLint OK ; `check-mojibake` OK.
3. Recherche et pills fonctionnent sur les chaînes localisées.
4. Entrée CHANGELOG.

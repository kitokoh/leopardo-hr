# Feature Specification: SEO racine localisé ×4 + code mort vitrine (issues #4707, #4708)

**Feature Branch**: `fix/4707-4708-web-seo-deadcode`

**Created**: 2026-08-17

**Status**: Draft → Implemented

**Input**: Audit 360° 2026-08-16/17 (vitrine Next.js) :
- #4707 : `layout.tsx` keywords toujours FR pour les 4 locales ; alt og:image FR en dur (`opengraph-image.tsx` + metadata) ; `JsonLd.tsx` description Organisation FR + `priceCurrency: 'EUR'` codé en dur alors que `modules/vitrine/data/currency.ts` existe ;
- #4708 : `lib/monitoring.ts` (503 lignes) sans importeur ; export `HeroSection()` mort (les pages utilisent `sections/HeroSection`) ; `common/Divider.tsx` mort (déjà retiré sur main avant ce PR) ; `PLAN_CONFIG` (checkout) porte `features` FR + `employeeLimit` jamais rendus (l'UI utilise `planCopy.features` localisé).

## Décision

1. `ROOT_METADATA` étendu : `keywords: string[]` + `ogImageAlt` ×4 locales ; `generateMetadata` consomme `rootMeta.keywords` et `rootMeta.ogImageAlt` — plus aucun FR en dur pour EN/TR/AR.
2. `JsonLd` : map `organizationDescription` ×4 locales ; `priceCurrency` ← `PRICING_CURRENCY` (export canonique depuis `currency.ts`, EUR = devise des prix machine ADR-0014 — pas de conversion menteuse).
3. Suppression des symboles morts (grep-audité) : `lib/monitoring.ts`, `HeroSection()` + imports orphelins (`AnimatedCounter`, `statIcons` jamais référencés), `features`/`employeeLimit` de `PLAN_CONFIG`.
4. Aucun changement de comportement visible — tsc/eslint/jest verts.

## User Scenarios & Testing

### US1 — Crawlers EN/TR/AR reçoivent des keywords/alt non-FR (P3, #4707)
**Independent Test**: tsc 0 erreur + grep du diff (aucun keywords FR restant dans la racine).
1. **Given** `?lang=en`, **When** `generateMetadata` racine, **Then** keywords EN (« HR SaaS »…) et alt og:image EN.
2. **Given** `?lang=ar`, **Then** keywords AR et alt AR.
3. **Given** JSON-LD en locale tr, **Then** description TR ; `priceCurrency` toujours `EUR` (prix machine).

### US2 — Le code mort vitrine disparaît (P3, #4708)
**Independent Test**: 452 tests vitrine verts, tsc 0, eslint 0.
1. **Given** suppression de `lib/monitoring.ts`, **When** build, **Then** aucune référence restante (grep).
2. **Given** checkout `?plan=operations`, **Then** l'UI rend `planCopy.features` localisé (inchangé).

## Edge Cases

- `organizationDescription[locale]` inconnu → fallback FR (`?? organizationDescription.fr`).
- Le prix JSON-LD reste le prix machine EUR (un changement de `priceCurrency` vers TRY/DZD mentirait sur le montant).
- `HeroSection.tsx` conserve `QuickTrialEmailForm` (seul import vivant, `page.tsx:37`).

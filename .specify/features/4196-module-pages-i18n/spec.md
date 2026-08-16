# Feature Specification: Pages modules localisées (issue #4196)

**Feature Branch**: `fix/4196-module-pages-i18n`
**Created**: 2026-08-16
**Status**: Draft → Lot 1 implémenté
**Input**: Audit 360° 2026-08-16 — `content.ts` (modulePageContent, 721 lignes) 100 % FR pour les 4 pages modules (employes/comptabilite/documents/marketing) malgré des metadata localisées (#4196).

## Problème

- Le contenu (hero, problem, solution, case studies, témoignages, FAQ, CTA) des 4 pages modules est un littéral FR unique, servi tel quel aux 4 locales.
- Les pages sont data-driven (`modulePageContent.<module>`) — seule la donnée est monolingue.

## Décision (lot 1)

1. `modulePageContentByLocale: Record<AppLocale, Partial<ModulePageContent>>` — `en/tr/ar` fournissent leurs modules traduits ; `getModulePageContent(locale)` fusionne la locale sur le FR (fallback par module, pattern des lots #4206/#4191).
2. Lot 1 : module **employes** traduit ×3 (en/tr/ar) ; les autres modules retombent sur FR jusqu'aux lots suivants.
3. `employes/page.tsx` : `getModulePageContent(useVitrineLocale().locale).employes`.
4. Test garde `module-content-i18n.test.ts` : modules fournis = clés FR exactes ; fusion complète pour les 4 locales.

## User Scenarios & Testing

**Independent Test**: `npx jest src/modules/vitrine/lib/__tests__/module-content-i18n.test.ts`

**Acceptance Scenarios**:
1. **Given** `?lang=en|tr|ar`, **When** `/employes` s'affiche, **Then** hero/problem/solution/case studies/témoignages/FAQ/CTA sont dans la langue active.
2. **Given** une locale non-fr, **When** `/documents` s'affiche (module non encore traduit), **Then** le contenu FR s'affiche (fallback explicite, aucune page blanche).
3. **Given** un module traduit incomplet, **Then** le test garde échoue (clés ≠ FR).

## Functional Requirements

1. `content.ts` : `modulePageContentEn/Tr/Ar` (employes complet), `modulePageContentByLocale`, `getModulePageContent(locale)`.
2. `employes/page.tsx` : hook locale.
3. Test : égalité des clés par module fourni + fusion complète.
4. CHANGELOG : entrée `### Fixed`/`### Changed`.

## Success Criteria

- /employes 100 % localisé ×4 locales ; fallback FR propre pour les modules non traduits.
- `tsc --noEmit`, `eslint`, tests verts.
- Issue #4196 suivie : lots 2-4 (documents, comptabilite, marketing) dans la même issue.

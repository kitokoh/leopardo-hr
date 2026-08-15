# Plan — Aligner les CTA de conversion et les E2E signup avec l'i18n (#3834)

**Branch**: `fix/3834-cta-e2e-i18n`

## Objectif

Suite E2E vitrine verte et honnête : les tests sélectionnent les CTA via un
contrat stable (href/testid), les messages feature-lock sont localisés
(4 locales), aucune chaîne FR en dur dans le panneau.

## Étapes

1. **i18n** — ajouter 4 clés `featureLocked*` (AdminHint, PlanRoleTitle,
   PlanRoleBody, Cta) dans `front/web/src/lib/i18n.ts` × 4 locales + type.
2. **Composant** — `FeatureLockedPanel` consomme les clés ; `data-testid`
   stable ; `aria-label` du cadenas sidebar localisé.
3. **Tests feature-gates** — corriger les apostrophes attendues (alignement
   sur les catalogues), assertions via `getByTestId`.
4. **Tests conversion-funnel** — helpers `a[href^=...]` (robuste `?lang=`),
   `Promise.all(click, waitForURL)` pour demo/contact, timeouts dev honnêtes.
5. **Validation locale** — `tsc --noEmit`, `eslint --max-warnings 0`,
   Playwright chromium (feature-gates puis conversion-funnel).
6. **Livraison** — CHANGELOG + spec-kit, PR avec `Closes #3834`.

## Risques

- Régression #3821 (`?lang=` sur les liens internes) : absorbée par les
  sélecteurs `href^=`.
- Cold compile Next dev : timeouts élargis documentés (150 s test / 120 s wait).

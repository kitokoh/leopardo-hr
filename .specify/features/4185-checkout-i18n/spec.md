# Feature Specification: Checkout & Success i18n (issues #4185, #4218)

**Feature Branch**: `fix/4185-checkout-i18n`
**Created**: 2026-08-16
**Status**: Draft → À implémenter
**Input**: Audit 360° 2026-08-16 — `front/web/src/app/(landing)/checkout/page.tsx` (~1040 lignes) et `checkout/success/page.tsx` (~400 lignes) sont 100 % codées en dur en FR : les 4 locales de la vitrine (fr/en/tr/ar) reçoivent le même contenu français dans le tunnel payant entier (récapitulatif, compte, paiement, confirmation). Issue #4218 : résiduel #3248 hors périmètre — seuls /docs (déjà localisé, PR #4240) et /checkout(/success) (objet de cette spec).

## Problème

- `PLAN_CONFIG` embarque des chaînes FR (`features`, `employeeLimit`, libellés) utilisées telles quelles dans tous les locaux.
- Tous les textes UI du tunnel (étapes, formulaire compte, formulaire paiement, badges de confiance, état « essai guidé » du plan Free, page de confirmation, étapes suivantes) sont des littéraux FR.
- Aucun test ne protège la complétude des locales (une chaîne FR peut réapparaître silencieusement).

## Décision

1. **Module de données localisé** `front/web/src/modules/vitrine/data/checkout.ts` : `CheckoutCopy` typé (interfaces complètes) + `checkoutCopyByLocale` (fr/en/tr/ar) + accesseur `getCheckoutCopy(locale)` avec fallback en. Vocabulaire aligné sur `pricing.ts` (#2977/#3919) et `docs-page.ts`.
2. **PLAN_CONFIG allégé** : structure seule (icônes, couleurs, prix, savings, trialDays) ; `label`/`features`/`employeeLimit` viennent du copy par locale.
3. **Pages refactorées** (`checkout/page.tsx`, `checkout/success/page.tsx`) : tous les littéraux FR remplacés par des références au copy ; le comportement (étapes, validation, soumission Stripe, sandbox) est strictement inchangé.
4. **Test garde de complétude** : toutes les locales exposent exactement les mêmes clés que `en` (prévention du drift i18n, même pattern que `check-i18n-diff.js` côté API).
5. Corrections FR au passage : accents manquants (« bientot » → « bientôt », « Schema » → « Schéma »).

## User Scenarios & Testing

### User Story 1 — Un prospect en TR ou AR souscrit (Priority: P1)
**Independent Test**: `npm run test -- checkout-i18n` (test de complétude) + `tsc --noEmit` + `eslint`.

**Acceptance Scenarios**:
1. **Given** un visiteur avec `?lang=tr` ou `?lang=ar`, **When** il ouvre `/checkout?plan=pilot`, **Then** 100 % des textes du tunnel sont dans sa langue (étapes, champs, erreurs, badge de confiance, CTA).
2. **Given** une souscription confirmée, **When** la page `/checkout/success?plan=pilot&billing=annual` s'affiche, **Then** badge, titre, récapitulatif et « prochaines étapes » sont localisés.
3. **Given** l'URL profonde `/checkout?plan=free`, **When** l'état « essai guidé » s'affiche, **Then** ses 5 textes sont localisés (FR inclus).
4. **Given** le copy FR, **Then** les accents français sont corrects (aucun mot dégradé).

## Edge Cases

- Plan Free (`/checkout?plan=free`) : état « essai guidé » — aucun prix, aucun formulaire ; texte localisé.
- Enterprise (« Sur devis » / « Custom quote » / « Özel teklif » / « حسب العرض ») : le libellé quote est localisé et le prix absent.
- Locale inconnue : `getCheckoutCopy` retombe sur `en`.
- Sandbox (dev/staging) : les textes sandbox sont localisés mais ne sont jamais affichés en production (#2628).

## Functional Requirements

1. `data/checkout.ts` : interfaces `CheckoutCopy`, `CheckoutPlanCopy`, `CheckoutPlanKey` ; `checkoutCopyByLocale[fr|en|tr|ar]` ; `getCheckoutCopy()`.
2. `checkout/page.tsx` : suppression des littéraux FR (features/labels/UI/erreurs/trust/état free) au profit du copy ; hooks `useVitrineLocale()` + `getCheckoutCopy`.
3. `checkout/success/page.tsx` : badge, titre, sous-titre, récapitulatif (6 lignes), notice email, 3 étapes suivantes, CTA, note support localisés.
4. Test `src/modules/vitrine/data/__tests__/checkout-i18n.test.ts` : clés identiques entre locales + présence des 4 locales + aucun littéral FR orphelin dans les pages (`rg` ciblé sur les fichiers du tunnel).
5. CHANGELOG.md : entrée sous `## [Unreleased]` (`### Fixed` / `### Changed`).

## Success Criteria

- Aucun littéral FR utilisateur restant dans `checkout/page.tsx` ni `checkout/success/page.tsx` (vérifiable par `rg`).
- `tsc --noEmit`, `eslint`, et le test de complétude verts.
- Tunnel payant fonctionnellement identique (aucun changement de comportement ni de style).
- Issues #4185 et #4218 fermées par la PR (`Closes #4185, #4218`).

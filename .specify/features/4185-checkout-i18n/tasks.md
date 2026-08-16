# Tasks — Checkout & Success i18n (issues #4185, #4218)

## T1 — Module de données localisé
- [x] Créer `front/web/src/modules/vitrine/data/checkout.ts` : types + `checkoutCopyByLocale` (fr/en/tr/ar) + `getCheckoutCopy()`.
- [x] Sections : meta, navigation, étapes, plan choisi, quote, périodes, badges de confiance, Google, récap, compte (labels/placeholders/erreurs), paiement (sandbox, carte, récap, légal), état Free, succès (récap, notice email, étapes suivantes, CTA, support), plans (features par locale).

## T2 — Refactor `checkout/page.tsx`
- [x] `PLAN_CONFIG` réduit à la structure (icônes/couleurs/prix/savings/trialDays).
- [x] `PlanSummaryCard` / `TrustBadges` / `GoogleButton` / `StepRecap` / `StepAccount` / `StepPayment` / `CheckoutInner` : textes via `getCheckoutCopy(locale)`.
- [x] État « essai guidé » (`?plan=free`) localisé.
- [x] Erreurs de validation + erreurs réseau localisées.
- [x] Aucun littéral FR utilisateur restant (`rg` vérifié).

## T3 — Refactor `checkout/success/page.tsx`
- [x] `nextSteps` → `NEXT_STEP_META` structurel + textes via copy.
- [x] Badge (sandbox/confirmé), titre, sous-titre (plan/période), carte récap (6 lignes), notice email, étapes suivantes, CTA, note support localisés.
- [x] `title` de copie de référence localisé.

## T4 — Test garde de complétude
- [ ] Créer `front/web/src/modules/vitrine/data/__tests__/checkout-i18n.test.ts` :
  - [ ] clés identiques entre `fr/en/tr/ar` (deep keys vs `en`) ;
  - [ ] `getCheckoutCopy` fallback `en` pour locale inconnue ;
  - [ ] aucun littéral FR orphelin dans les 2 pages (scan `rg` des accents/littéraux).

## T5 — Validation & livraison
- [ ] `tsc --noEmit` + `eslint` sur les 3 fichiers modifiés.
- [ ] `vitest` sur le nouveau test.
- [ ] CHANGELOG.md : entrée `### Changed` / `### Fixed`.
- [ ] PR `fix(web): checkout + success 100 % localisés — 4 locales (Closes #4185, #4218)`.

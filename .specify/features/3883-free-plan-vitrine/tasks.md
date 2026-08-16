# Tasks: Plan Free visible sur la vitrine (Closes #3883)

**Spec**: `.specify/features/3883-free-plan-vitrine/spec.md`

- [x] T1. Claim : issue #3883 self-assignée + branche `fix/3883-free-plan-vitrine` poussée avec commit de claim (protocole #2400)
- [x] T2. `pricing.ts` — plan Free ajouté en tête des 4 locales (0 €/0 €, ≤ 5 employés, fonctionnalités alignées colonne `free` du comparateur)
- [x] T3. CTA Free → `/signup?source=pricing_free` sur `/pricing` (`getPlanHref`) et la section home (`getPlanCtaHref`) — zéro CTA vers `/checkout?plan=free`
- [x] T4. Grille home `PricingSection` : `sm:grid-cols-2 lg:grid-cols-4`
- [x] T5. FAQ `pricing-faq.ts` : entrée « Free » ×4 locales + plafonds 5/30/250/illimité (question facturation)
- [x] T6. Test `pricing-checkout-alignment` : 4 plans, Free ↔ checkout 0/0 ; `pricing-plan-routing` inchangé
- [ ] T7. Vérifs locales : `npm run lint` + `tsc` + tests vitrine (front/web)
- [ ] T8. CHANGELOG (`### Fixed`… `[Unreleased]`) + `docs/specifications/ISSUE_3883.md`
- [ ] T9. PR `fix(web): plan Free de retour sur la vitrine — schéma canonique complet (Closes #3883)` → CI verte → merge → suppression branche

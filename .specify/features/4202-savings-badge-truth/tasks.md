# Tasks: Badge économies annuel — vérité + dark mode case-studies (Closes #4202, volet 1 & 3)

**Spec**: `.specify/features/4202-savings-badge-truth/spec.md`

- [x] T1. Claim : issue #4202 self-assignée + branche `fix/4202-savings-badge-truth`
- [x] T2. `PricingSection.tsx` — map `savingsLabel` supprimé, usage `copy.pricing.annualSavings`
- [x] T3. `vitrine-locale.ts` — `annualSavings` ×4 locales → « jusqu'à 20 % »
- [x] T4. `CaseStudyClient.tsx` — `useDarkMode()` (persistance thème)
- [x] T5. Vérifs : tsc + eslint + jest (480) + mojibake + validate i18n
- [ ] T6. CHANGELOG + `docs/specifications/ISSUE_4202.md`
- [ ] T7. PR → CI verte → merge → suppression branche

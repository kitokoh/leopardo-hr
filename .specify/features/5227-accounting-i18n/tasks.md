# Tasks: i18n ×4 du module Comptabilité (Closes #5227)

Issue : #5227 · Branche : `mod/accounting/5227-accounting-i18n` · Spec : `spec.md`

- [ ] 1. Catalogues `api/lang/{fr,en,tr,ar}/accounting.php` : + 15 clés `error_*` + 3 clés `validation_*` (parité ×4)
- [ ] 2. Catalogues `api/lang/{fr,en,tr,ar}/errors.php` : + `PAYMENT_EXCEEDS_TOTAL` + `PAYMENT_ON_UNSENT_DOCUMENT`
- [ ] 3. `DocumentWorkflowService.php` : 14 littéraux FR → `__('accounting.error_*')`
- [ ] 4. `PaymentRegistrationService.php` : littéral FR → `__('accounting.error_payment_amount_positive')`
- [ ] 5. `PaymentRegisterRequest.php` : `messages()` → clés `accounting.validation_*`
- [ ] 6. Garde CI `dev-hub/tools/check-accounting-i18n.py` (miroir #5257)
- [ ] 7. `accounting-ci.yml` : job `i18n-scan` + chemins de déclenchement (`api/lang/**`, garde)
- [ ] 8. Tests `api/tests/Feature/Accounting/AccountingI18nTest.php`
- [ ] 9. `docs/specifications/ISSUE_5227.md` + CHANGELOG [Unreleased]
- [ ] 10. Vérifs locales (garde python + check-i18n-diff vs main) + push + PR `Closes #5227`

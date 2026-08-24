# Plan: i18n ×4 du module Comptabilité (Closes #5227)

**Spec**: `.specify/features/5227-accounting-i18n/spec.md`
**Issue**: #5227

## Architecture

Aucun changement de schéma. La localisation suit le pattern #5257 (Payroll) :
messages résolus au point de levée via `__('accounting.*')`, catalogue Laravel
`api/lang/{fr,en,tr,ar}/accounting.php`, codes d'erreur API via
`api/lang/{fr,en,tr,ar}/errors.php` (renderer #4171).

```
api/app/Modules/Accounting/
├── Application/Services/DocumentWorkflowService.php   # 14 littéraux FR → __('accounting.error_*')
├── Infrastructure/Services/PaymentRegistrationService.php  # 1 littéral FR → __('accounting.error_payment_amount_positive')
├── Interfaces/Api/V1/Requests/PaymentRegisterRequest.php   # messages() → clés accounting.validation_*
└── Domain/Exceptions/PaymentExceedsTotalException.php       # + code errors.PAYMENT_EXCEEDS_TOTAL (renderer)
    Domain/Exceptions/PaymentOnUnsentDocumentException.php   # + code errors.PAYMENT_ON_UNSENT_DOCUMENT (renderer)

api/lang/{fr,en,tr,ar}/accounting.php    # + 15 clés error_* + 3 clés validation_* (parité ×4)
api/lang/{fr,en,tr,ar}/errors.php        # + PAYMENT_EXCEEDS_TOTAL + PAYMENT_ON_UNSENT_DOCUMENT (parité ×4)
api/tests/Feature/Accounting/AccountingI18nTest.php   # NOUVEAU — parité, messages localisés, labels PDF
dev-hub/tools/check-accounting-i18n.py   # NOUVEAU — garde CI (miroir check-payroll-i18n.py)
.github/workflows/accounting-ci.yml      # + job i18n-scan + chemins api/lang, garde
docs/specifications/ISSUE_5227.md        # NOUVEAU — spec issue (règle repo)
CHANGELOG.md                             # + entrée [Unreleased]
```

## Décisions

1. **Localisation au point de levée** (pattern #5257) : `throw new
   DocumentWorkflowException(__('accounting.error_*'))` — la locale applicative
   est déjà résolue par `SetLocale` middleware avant le contrôleur. Pas de
   refonte du renderer #4171 (hors périmètre).
2. **Codes `errors.*`** : `PAYMENT_EXCEEDS_TOTAL` et `PAYMENT_ON_UNSENT_DOCUMENT`
   ajoutés aux 4 catalogues (messages génériques sans interpolation — le
   renderer ne passe pas de paramètres, design #4171).
3. **Garde CI** : même contrat que #5257 — (a) aucun littéral `'message' => '...'`
   non localisé dans le module, (b) aucun littéral français dans `throw ...('...')`
   (hors codes machine MAJUSCULES et clés connues), (c) parité des clés
   `accounting.php` ×4, (d) présence des libellés PDF ×4 (6 types × 6 statuts),
   (e) présence des codes `errors.*` des DomainException du module ×4.
4. **Tests** : assertions sur le rendu (message 422 localisé fr/en), la parité
   des catalogues et les labels PDF via `DocumentPdfRenderer::buildViewData`
   (golden sans binaire PDF, pattern `DocumentPdfRendererTest`).

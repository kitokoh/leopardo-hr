# Issue #5227 — i18n ×4 du module Comptabilité (UI + documents)

**Statut** : livrée (PR `mod/accounting/5227-accounting-i18n`).
**Références** : spec `.specify/features/5227-accounting-i18n/spec.md` ·
garde `dev-hub/tools/check-accounting-i18n.py` · test
`api/tests/Feature/Accounting/AccountingI18nTest.php`.

## Objectif

Toutes les chaînes utilisateur du module Comptabilité (API + PDF + UI web)
passent par les catalogues fr/ar/tr/en ; aucune chaîne hardcodée (règle #2755) ;
parité des clés ×4 ; RTL arabe vérifié (PDF + web).

## DoD — vérifié sur main + branche

- [x] **0 chaîne hardcodée** : garde CI `check-accounting-i18n.py` (job
      `i18n-scan` de `accounting-ci.yml`) + garde globale `check-i18n-diff.js`
      (PA2-I18N-014) sur les surfaces surveillées.
- [x] **Parité des clés ×4** : `api/lang/{fr,en,tr,ar}/accounting.php`
      (50 clés identiques ×4, verrouillé par test + garde) ;
      `errors.php` (210 codes ×4, dont `PAYMENT_EXCEEDS_TOTAL` et
      `PAYMENT_ON_UNSENT_DOCUMENT` ajoutés).
- [x] **RTL arabe** : PDF documents — `dir="rtl"` + fonte DejaVu Sans
      (déjà livré #5224, vérifié par `DocumentPdfRendererTest`) ; labels
      arabes verrouillés par `AccountingI18nTest` (golden `buildViewData`).
- [x] **UI web** : `AccountingSettingsView.vue` cléée `accounting.settings.*`
      (37 clés ×4, livré #5232) ; aucune chaîne hardcodée ajoutée.

## Périmètre livré

| Surface | Changement |
|---|---|
| `api/app/Modules/Accounting/**` | 16 littéraux français → `__('accounting.*')` (DocumentWorkflowService, PaymentRegistrationService, UpdateAccountingSettingsRequest, VatDeclarationService, AccountingReportController) |
| `api/lang/{fr,en,tr,ar}/accounting.php` | +17 clés `error_*` / `validation_*` |
| `api/lang/{fr,en,tr,ar}/errors.php` | +`PAYMENT_EXCEEDS_TOTAL`, +`PAYMENT_ON_UNSENT_DOCUMENT` |
| `dev-hub/tools/check-accounting-i18n.py` | garde CI dédiée (miroir #5257 étendu) |
| `.github/workflows/accounting-ci.yml` | job `i18n-scan` + chemins `api/lang/**` |
| `api/tests/Feature/Accounting/AccountingI18nTest.php` | 10 tests (parité, messages 422 fr/en, codes erreur, labels PDF fr/en/ar) |

## Hors périmètre (assumé)

Messages internes (logs, commandes artisan, exceptions machine-code
`INVALID_*`/`DOCUMENT_*` jamais exposées au client — design #4171) et données
par défaut (`AccountingSettingsDefaults`) : non exposés à l'utilisateur, même
règle que le précédent #5257.

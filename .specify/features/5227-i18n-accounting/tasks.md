# Tasks — i18n ×4 module Comptabilité (Closes #5227)

## 1. Catalogues backend

- [ ] `api/lang/{fr,en,tr,ar}/accounting.php` : sections `validation.*`, `errors.wf_*`, `errors.payment_amount_positive`, `tva_label_standard`, `tva_label_reduced` (parité ×4).
- [ ] `api/lang/{fr,en,tr,ar}/errors.php` : 7 codes comptabilité ×4.

## 2. PDF RTL arabe

- [ ] `DocumentPdfRenderer` : `ensureArabicFonts()` (Almarai, idempotent, fallback).
- [ ] `pdf/accounting-document.blade.php` : police Almarai RTL + helper `$t`/`$shape` (ArabicPdfText) + alignement RTL cellules.

## 3. Messages API/validation/erreurs

- [ ] 4 exceptions RuntimeException → DomainException (422, code) ; messages internes neutralisés.
- [ ] `DocumentWorkflowService` : 14 littéraux → clés.
- [ ] `PaymentRegistrationService` : message → clé.
- [ ] `PaymentRegisterRequest` + `UpdateAccountingSettingsRequest` : messages → clés.
- [ ] `VatDeclarationService` + `AccountingReportController` : code `ACCOUNTING_VAT_PERIOD_INVALID`.

## 4. Labels TVA par défaut

- [ ] `AccountingSettingsDefaults` : `label_key` sur chaque taux.
- [ ] `AccountingSettingsController::serialize()` : traduction label par `label_key`.
- [ ] `AccountingSettingsView.vue` : label_key par défaut, suppression à l'édition, préservation au save.
- [ ] Catalogues admin ×4 + checksums via `sync-web.js`.

## 5. Tests & livrables

- [ ] `AccountingI18nMessagesTest` (Feature, US2 ×4).
- [ ] Extension `DocumentPdfRendererTest` (US1).
- [ ] Extension `AccountingSettingsTest` (US3).
- [ ] CHANGELOG.md entrée.
- [ ] Gardes locales : `php -l` (si dispo), `check-i18n-diff.js`, parité admin.

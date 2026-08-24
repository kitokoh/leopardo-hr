# Feature Specification: i18n ×4 du module Comptabilité — UI web + documents PDF (Closes #5227)

**Feature Branch**: `mod/accounting/5227-i18n-x4`
**Issue**: #5227 (P2, module Accounting)
**Presets**: `leopardo-multitenancy` (aucune table ajoutée), i18n ×4

## Contexte

Le module Comptabilité (Phase A/B) a été livré avec l'i18n ×4 sur les surfaces
principales (settings UI + template PDF `accounting-document` via
`api/lang/{fr,en,tr,ar}/accounting.php`). L'audit 2026-08-24 (issue #5227,
règle #2755) révèle 4 classes d'écarts restantes :

1. **Rendu PDF arabe (RTL) cassé** — `pdf/accounting-document.blade.php` garde
   `DejaVu Sans` et des chaînes arabes non shapingées : dompdf rend les lettres
   déconnectées et dans l'ordre logique (inversé visuellement). Le bulletin de
   paie a résolu le même problème (#5242 : police Almarai + `ArabicPdfText::shape`).
2. **Messages API/validation français en dur** — `PaymentRegisterRequest`
   (`amount.min`, `method.in`), closure `UpdateAccountingSettingsRequest`
   (série inconnue), `PaymentRegistrationService` (InvalidArgumentException),
   `DocumentWorkflowService` (14 messages métier) → littéraux FR bruts envoyés
   aux tenants en/tr/ar (même classe que #4592/#3237).
3. **Codes d'erreur du domaine absents du catalogue `errors.php`** —
   `PAYMENT_EXCEEDS_TOTAL`, `PAYMENT_ON_UNSENT_DOCUMENT`,
   `CREDIT_NOTE_REQUIRES_SOURCE_INVOICE`, `DELIVERY_NOTE_REQUIRES_DELIVERY_DATE`,
   `DOCUMENT_NOT_FULLY_PAID`, `INVALID_DOCUMENT_TRANSITION` : le handler global
   (#4171) renvoie un message générique faute de clé. 4 de ces exceptions
   étendent `RuntimeException` (500) au lieu de `DomainException` (422 localisée).
4. **Labels TVA par défaut en dur** — `AccountingSettingsDefaults::TVA_RATES_BY_COUNTRY`
   stocke des labels français (`TVA standard`, `TVA réduite`) affichés tels quels
   dans l'UI settings pour toutes les locales ; aucun `label_key` stable.

DoD #5227 : 0 chaîne hardcodée sur les surfaces du module (garde
`check-i18n-diff.js` PA2-I18N-014 + scan `i18n-debt.js`) ; parité des clés ×4
(garde `LangCatalogParityTest` #4293 + `validate.js` #4805 pour les catalogues
admin) ; RTL arabe vérifié (PDF + web admin — la direction du document est déjà
appliquée par `stores/locale.js` pour le web).

## User Stories

### US1 — Document PDF en arabe lisible (RTL)

En tant que comptable d'un tenant `ar`, je reçois un PDF de facture/devis/avoir
dont le texte arabe est connecté (shaping contextuel) et aligné à droite, avec
une police arabe (Almarai) — pas des lettres déconnectées dans un rendu LTR.

**Acceptance Scenarios** :
1. Given un document comptable émis, When rendu avec `document_language=ar`,
   Then le HTML rendu porte `dir="rtl"`, `font-family: Almarai` et les libellés
   arabes shapingés (`ArabicPdfText::shape`).
2. Given le même document en fr/en/tr, Then `font-family: DejaVu Sans` et aucun
   shaping (texte latin inchangé).
3. Le template ne contient aucun littéral hors catalogue (garde PA2-I18N-014).

### US2 — Erreurs API/validation localisées ×4

En tant que tenant en/tr/ar, je reçois les messages 422 du module Comptabilité
dans ma langue (Accept-Language / ?lang), pas en français brut.

**Acceptance Scenarios** :
1. Given POST `/accounting/documents/{id}/payments` avec `amount=0`, When
   Accept-Language: ar, Then erreur `amount` en arabe (clé
   `accounting.validation.amount_min`).
2. Given PUT `/accounting/settings` avec une clé `number_series` inconnue, When
   Accept-Language: tr, Then erreur `number_series.XXX` en turc avec
   interpolation `:key`/`:allowed`.
3. Given un paiement excédant le TTC, When Accept-Language: en, Then 422
   `PAYMENT_EXCEEDS_TOTAL` traduit en anglais (`errors.PAYMENT_EXCEEDS_TOTAL`).
4. Given un envoi de brouillon sans ligne, When Accept-Language: en, Then 422
   message workflow en anglais (`accounting.errors.wf_send_no_lines`).
5. Given GET `/accounting/reports/vat-declaration?period=2026-13`, When
   Accept-Language: ar, Then 422 validation `period` (catalogue global
   `validation.php`, localisé) ; la garde service `ACCOUNTING_VAT_PERIOD_INVALID`
   (DomainException 422 + code au catalogue `errors.php` ×4) couvre les appels
   hors validation (défense en profondeur, testée au niveau service).

### US3 — Labels TVA par défaut localisés

En tant que comptable d'un tenant tr/ar/en, les taux de TVA par défaut du
paramétrage s'affichent dans ma langue (label stable via `label_key`), et je
peux toujours personnaliser le label (saisie libre → label custom sans `label_key`).

**Acceptance Scenarios** :
1. Given une entreprise provisionnée (pays DZ), When GET `/accounting/settings`
   Accept-Language: en, Then `data.tva_rates[0]` = `{label: "Standard VAT",
   label_key: "standard", rate: 19}`.
2. Given l'UI settings en arabe, Then le label standard s'affiche en arabe.
3. Given un label personnalisé saisi par l'utilisateur, Then il est conservé tel
   quel (pas de `label_key`).

## Requirements

- **FR-001** — `api/resources/views/pdf/accounting-document.blade.php` :
  `font-family: {{ $rtl ? 'Almarai' : 'DejaVu Sans' }}`, libellés passés par un
  helper `$t`/`$shape` qui applique `App\Modules\Payroll\Infrastructure\Pdf\ArabicPdfText::shape()`
  quand `$rtl`, alignement RTL des cellules (`@if($rtl) th, td { text-align: right; }`).
- **FR-002** — `DocumentPdfRenderer` : enregistrement idempotent de la police
  Almarai (même pattern `ensureArabicFonts()` que `PaySlipPdfGenerator`, fonts
  committées dans `api/resources/fonts/`), absent → fallback DejaVu sans crash.
- **FR-003** — `api/lang/{fr,en,tr,ar}/accounting.php` : section `validation.*`
  (`amount_min`, `method_invalid`, `series_unknown` avec `:key`/`:allowed`),
  section `errors.wf_*` (14 messages workflow), `errors.payment_amount_positive`,
  `tva_label_standard`, `tva_label_reduced`. Parité ×4 (garde #4293).
- **FR-004** — `api/lang/{fr,en,tr,ar}/errors.php` : 7 codes ajoutés ×4 —
  `PAYMENT_EXCEEDS_TOTAL`, `PAYMENT_ON_UNSENT_DOCUMENT`,
  `CREDIT_NOTE_REQUIRES_SOURCE_INVOICE`, `DELIVERY_NOTE_REQUIRES_DELIVERY_DATE`,
  `DOCUMENT_NOT_FULLY_PAID`, `INVALID_DOCUMENT_TRANSITION`,
  `ACCOUNTING_VAT_PERIOD_INVALID`.
- **FR-005** — 4 exceptions de domaine passent de `RuntimeException` à
  `App\Exceptions\DomainException` (422, code machine) : `CreditNoteRequiresSourceInvoiceException`,
  `DeliveryNoteRequiresDeliveryDateException`, `DocumentNotFullyPaidException`,
  `InvalidDocumentTransitionException`. Messages internes neutralisés (le handler
  #4171 ne fuite jamais le message brut). `PaymentExceedsTotalException`/
  `PaymentOnUnsentDocumentException` inchangées (déjà DomainException).
- **FR-006** — `DocumentWorkflowService` : les 14 littéraux → `__('accounting.errors.wf_*')`.
  `PaymentRegistrationService` : `__('accounting.errors.payment_amount_positive')`.
  `PaymentRegisterRequest`/`UpdateAccountingSettingsRequest` : messages via
  clés `accounting.validation.*` (interpolations Laravel `:key`/`:allowed`).
- **FR-007** — `VatDeclarationService` : remplace `InvalidArgumentException`
  (clé-string `accounting.vat_period_invalid`) par `DomainException` 422 code
  `ACCOUNTING_VAT_PERIOD_INVALID` ; `AccountingReportController` simplifié
  (le handler global rend le message localisé).
- **FR-008** — `AccountingSettingsDefaults` : chaque taux de TVA porte un
  `label_key` stable (`standard`/`reduced`) ; `AccountingSettingsController::serialize()`
  traduit le label par `label_key` selon la locale de la requête, label custom
  inchangé. Rétro-compat : les lignes existantes sans `label_key` sont servies
  telles quelles.
- **FR-009** — UI admin `AccountingSettingsView.vue` : label par défaut avec
  `label_key` (`standard`), personnalisation → `label_key` supprimé dès édition
  manuelle ; `label_key` préservé au save. Clés ajoutées aux catalogues admin
  `accounting.settings.tva_label_standard/reduced` + `rate_reduced_label` ×4,
  checksums #4805 régénérés via `shared/i18n/sync/sync-web.js`.
- **FR-010** — Tests : `AccountingI18nMessagesTest` (Feature — US2 ×4 locales),
  extension `DocumentPdfRendererTest` (US1 : font/shaping selon locale),
  extension `AccountingSettingsTest` (US3 : label_key traduit + custom préservé).
  Zéro régression `LangCatalogParityTest`/`validate.js`.

## Success Criteria

- `rg "Le montant doit être strictement positif|Seul un brouillon|Un avoir doit|Paiement refusé|TVA standard|TVA réduite" api/app api/resources/views/pdf/accounting-document.blade.php` → 0 résultat hors commentaires/catalogue.
- `php -l` vert sur les 8 fichiers lang modifiés + 6 fichiers de service/exceptions/requests.
- Réponse 422 `?lang=ar` = arabe (test), `PAYMENT_EXCEEDS_TOTAL` = anglais (test).
- PDF ar : HTML rendu avec Almarai + `dir=rtl` (test).
- `node dev-hub/tools/check-i18n-diff.js main <head>` → 0 signal sur le diff.
- `LangCatalogParityTest` vert (parité ×4 des nouveaux fichiers).
- PHPStan strict + Pint verts sur les fichiers modifiés (CI).

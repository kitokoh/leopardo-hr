# Feature Specification: i18n ×4 du module Comptabilité (UI + documents)

**Feature Branch**: `mod/accounting/5227-accounting-i18n`
**Created**: 2026-08-24 | **Status**: In progress
**Issue**: #5227 (P2, backend + web, i18n, Comptabilité)
**Spec**: `.specify/features/5227-accounting-i18n/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **i18n**. La branche ne touche
que `api/app/Modules/Accounting/**` (messages), `api/lang/**` (catalogues),
`dev-hub/tools/check-accounting-i18n.py` (garde), `.github/workflows/accounting-ci.yml`
et le CHANGELOG. Aucun chevauchement de fichiers avec les branches en cours
(#5270 multi-devises, #5288 wizard, #5230 dashboards, #5234 journal, #5239 bridge).

## Contexte

L'issue #5227 (Phase A du plan Comptabilité) exige : toutes les chaînes UI (web)
+ documents (PDF) en fr/ar/tr/en via les catalogues existants ; aucune chaîne
hardcodée (règle #2755). **DoD** : 0 chaîne hardcodée (scan CI) ; parité des
clés ×4 ; RTL arabe vérifié (PDF + web).

## Audit de l'existant (main, 2026-08-24) — déjà fait, rien à refaire

| Surface | État | Preuve |
|---|---|---|
| PDF documents ×4 + RTL | ✅ déjà livré (#5224, PR #5346) | `api/resources/views/pdf/accounting-document.blade.php` : 100 % `__('accounting.*')`, `dir="{{ $rtl ? 'rtl' : 'ltr' }}"`, fonte DejaVu Sans (glyphes arabes) |
| Catalogue `api/lang/{fr,en,tr,ar}/accounting.php` | ✅ 33 clés, parité ×4 | diff clés vide fr↔en/tr/ar |
| UI web (admin-dashboard) | ✅ déjà cléé | `AccountingSettingsView.vue` : `$t('accounting.settings.*')` ; `navigation.accountingSettings` présent ×4 (37 clés ×4) |
| Garde CI diff (PA2-I18N-014) | ✅ existante | `dev-hub/tools/check-i18n-diff.js` (bloque toute nouvelle chaîne hardcodée sur les surfaces surveillées) |

## Reste à faire (le gap réel)

1. **Messages API du module localisés** — les seules chaînes utilisateur encore
   hardcodées en français (rendues telles quelles en 422 / validation) :
   - `Application/Services/DocumentWorkflowService.php` : 14 littéraux français
     (`DocumentWorkflowException`, remontés par `AccountingDocumentController::workflowError`)
     → `__('accounting.error_*')` ;
   - `Infrastructure/Services/PaymentRegistrationService.php:47` :
     `InvalidArgumentException('Le montant du paiement doit être strictement positif.')`
     → `__('accounting.error_payment_amount_positive')` ;
   - `Interfaces/Api/V1/Requests/PaymentRegisterRequest.php` : 3 messages de
     validation français → clés `accounting.validation_*` ;
   - `Domain/Exceptions/PaymentExceedsTotalException` (code `PAYMENT_EXCEEDS_TOTAL`)
     et `PaymentOnUnsentDocumentException` (code `PAYMENT_ON_UNSENT_DOCUMENT`) :
     les messages bruts ne fuient JAMAIS (design #4171, renderer `errors.*` dans
     `bootstrap/app.php`) → ajouter les deux codes aux 4 `errors.php`.
2. **Garde CI module** : `dev-hub/tools/check-accounting-i18n.py` (miroir du
   `check-payroll-i18n.py` #5257) branché dans `accounting-ci.yml` — zéro chaîne
   hardcodée + parité ×4 + présence des libellés PDF (`document_type_*` ×6,
   `status_*` ×6) + présence des codes `errors.*` utilisés par les DomainException
   du module.
3. **Tests** : `api/tests/Feature/Accounting/AccountingI18nTest.php` — parité des
   catalogues, message d'erreur API localisé par locale (fr/en), codes `errors.*`
   localisés, labels PDF localisés par locale (golden via `buildViewData`, sans
   dépendre du binaire PDF).
4. **CHANGELOG** + entrée en tête d'`[Unreleased]` ; `Closes #5227` dans la PR.

## Hors périmètre

- Les messages internes (logs, commandes artisan, exceptions machine-code
  `INVALID_*`/`CREDIT_NOTE_*` jamais exposées au client) restent en français —
  ils ne sont pas des chaînes utilisateur (même règle que #5257).
- L'UI web elle-même (déjà cléée, cf. audit) et les catalogues
  `shared/i18n/locales` (inchangés : le web Next.js n'a pas encore d'écrans
  Comptabilité sur main).

# Tasks — Multi-devises + taux de change (issue #5270)

## Backend — `api/app/Modules/Accounting/**`

- [ ] `Domain/Support/AccountingCurrencies.php` — registre devises supportées (union CountryDefaults) : `supported()`, `isSupported()`, `normalize()`.
- [ ] `Domain/Contracts/CurrencyRateProviderInterface.php` — `rate(string $from, string $to): float`, `source(): string`.
- [ ] `Infrastructure/Services/ManualCurrencyRateProvider.php` — taux fourni à l'appel (default du module).
- [ ] `Application/Actions/DocumentCurrencyConverter.php` — `convertAmount()`, `convertTotals(AccountingDocument, string $referenceCurrency)`, arrondis half-up 2 décimales, identité quand devises égales, TVA calculée en devise document.
- [ ] `Application/Actions/AccountingCurrencyResolver.php` — `forCompany()`, `forContact()`.
- [ ] `Interfaces/Api/V1/AccountingCurrencyController.php` + `Requests/ConvertCurrencyRequest.php` — `POST /accounting/currency/convert`.
- [ ] `routes/modules/accounting.php` — route convert (middleware api.manager:comptable,principal).
- [ ] `Interfaces/Api/V1/Requests/StoreContactRequest.php` / `UpdateContactRequest.php` — validation devise ∈ registre.
- [ ] `Interfaces/Api/V1/AccountingContactController.php` — défaut devise à la création (résolveur).
- [ ] `Interfaces/Api/V1/Requests/UpdateAccountingSettingsRequest.php` — refactor `AccountingCurrencies::supported()`.

## Tests — `api/tests/Feature/Accounting/`

- [ ] `AccountingMultiCurrencyTest.php` — défauts devise contact (settings/pays), devise explicite, 422 devise inconnue, pas de surcharge au PUT ; endpoint convert (golden arrondis, identité, TVA, provider externe, RBAC, 422 taux invalide) ; isolation tenant.

## Contrat API

- [ ] `api/openapi.yaml` — path `/accounting/currency/convert` + schémas payload/résultat ; description devise contacts.
- [ ] `node dev-hub/tools/generate-openapi-sdk.mjs` — régénérer miroir `dev-hub/openapi/v1.yaml` + SDK (MANIFEST, JS, Python).

## Docs

- [ ] `CHANGELOG.md` — 1 ligne en tête d'`[Unreleased]` (Added).
- [ ] `docs/architecture/COMPTABILITE_CONCEPTION.md` — §4.1 multi-devises (sémantique taux, arrondis, ordre TVA).
- [ ] `docs/security/RBAC_ROUTE_MATRIX.md` — ligne route `/accounting/currency/convert`.

## Spec

- [x] `.specify/features/5270-multi-currency/spec.md`, `plan.md`, `tasks.md`.

## PR

- [ ] Branch `mod/accounting/5270-multi-currency`, `Closes #5270` dans le body.
- [ ] CI verte : accounting-ci (tests + coverage ≥ 70 %), tests.yml, phpstan-modules, phpstan-strict, Backend Quality, OpenAPI CI.
- [ ] Merge + suppression branche.

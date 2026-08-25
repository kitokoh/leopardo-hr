# Plan — Multi-devises + taux de change (issue #5270)

## Objectif

Rendre la facturation multi-devises exploitable sur le socle existant : devise validée et résolue par contact (défaut entreprise), conversion HT/TVA/TTC avec arrondis documentés (taux manuel + provider externe pluggable), endpoint utilitaire de conversion.

## Étapes

1. **Registre** — `Domain/Support/AccountingCurrencies` (union `CountryDefaults`, normalisation ISO 4217).
2. **Résolution de devise** — `Application/Actions/AccountingCurrencyResolver` : `forCompany()`, `forContact()` (chaîne contact → settings → pays).
3. **Conversion** — `Domain/Contracts/CurrencyRateProviderInterface` + `Infrastructure/Services/ManualCurrencyRateProvider` + `Application/Actions/DocumentCurrencyConverter` (arrondis half-up 2 décimales, TVA calculée en devise document, totaux convertis).
4. **Validation contacts** — `StoreContactRequest`/`UpdateContactRequest` : devise ∈ registre ; défaut à la création dans le contrôleur.
5. **Refactor settings** — `UpdateAccountingSettingsRequest` consomme `AccountingCurrencies` (suppression de la méthode privée dupliquée).
6. **Endpoint** — `POST /accounting/currency/convert` : `AccountingCurrencyController` + `ConvertCurrencyRequest` + route (RBAC comptable/principal) + OpenAPI + SDK.
7. **Tests** — `tests/Feature/Accounting/AccountingMultiCurrencyTest.php` (Feature, golden, isolation tenant, RBAC) ; couverture du gate ≥ 70 % du module.
8. **Docs** — CHANGELOG (1 ligne en tête d'[Unreleased]), `COMPTABILITE_CONCEPTION.md` §4.1 multi-devises (sémantique taux + arrondis), `RBAC_ROUTE_MATRIX.md` (route convert).

## Dépendances

- Socle #5221 mergé (colonnes présentes) ✅
- Aucune dépendance aux PRs en cours (#5352, #5341…) — le convertisseur est une brique pure consommable par #5226/#5352 à leur merge.
- CI : `accounting-ci.yml` (gate coverage ≥ 70 %), `tests.yml` (suite complète), `phpstan-modules`, `phpstan-strict` (delta), Backend Quality (Pint diff), OpenAPI CI (lint Redocly + couverture routes→spec + miroir/SDK).

## Risques

- Gate coverage : compensé par des tests exhaustifs du convertisseur/résolveur (golden).
- Conflits croisés module Accounting : périmètre limité à `api/app/Modules/Accounting/**` + docs/OpenAPI ; rebase avant PR.
- PHPStan strict level 8 : typage strict (pattern des fichiers existants du module).

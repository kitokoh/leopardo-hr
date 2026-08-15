# Plan technique — Bloc compliance mobile (backend)

## Analyse

`PayrollCycleController::myBalance()` et `mobileSummary()` ne portent pas le
bloc compliance ; `CountryRulesResolver` + `CountryRulesInterface`
(confidenceLevel/complianceWarning/complianceSource/verificationDate)
exposent tout ce qu'il faut (même shape que PayrollCalculationPresenter).

## Architecture

- Helper privé `complianceFor(Employee)` dans PayrollCycleController :
  pays via `currentCompany()` (bound par le middleware) sinon requête
  qualifiée `public.companies` ; résolution CountryRulesResolver ;
  fail-open `[]` sur exception.
- Ajout de `compliance` au payload de `myBalance()` et `mobileSummary()`.
- OpenAPI (api + miroir dev-hub) documenté.

## Décisions

1. Fail-open : un bloc informatif ne doit jamais casser le résumé paie.
2. Shape identique à la simulation (#1872) pour réutiliser le contrat.

## Tests

- PayrollCycleIntegrationTest : `test_balance_exposes_compliance_block` (DZ
  → pilot) et `test_mobile_summary_exposes_compliance_block` (MA → pilot).

## Suivi

- UI mobile (bandeau localisé) : issue dédiée — nécessite `flutter gen-l10n`
  (les fichiers générés ne contiennent pas encore les clés payrollConfidence*
  présentes dans les ARB).

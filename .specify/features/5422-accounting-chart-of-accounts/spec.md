# Feature Specification: Plan comptable par pays du module Comptabilité

**Feature Branch**: `mod/accounting/5422-consolidation`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5422 (consolidation Comptabilité — étape 2, profondeur)
**Spec**: `.specify/features/5422-accounting-chart-of-accounts/spec.md`

## Objectif

Registre immuable des comptes du grand livre par pays (facturation, trésorerie,
dépenses, bilan), consommable par le journal des écritures (#5234) et les
rapports (grand livre, balance, bilan). Pattern `PayrollCountryChartOfAccounts`
(#5256), zéro migration, zéro modification de settings.

## Livrable

- `AccountingChartOfAccounts` : 11 familles × 21 pays (PCG production,
  SYSCOHADA production, Tekdüzen/UK/US/CA pilot), fallback PCG.
- Tests golden `AccountingChartOfAccountsTest` (9 tests : couverture ×21 pays,
  golden par référentiel, fallback, complétude).
- Doc `docs/accounting/PLAN_COMPTABLE.md`.
- CHANGELOG une ligne.

## Hors périmètre (étapes suivantes #5422)

Consommation par le journal, grand livre, balance de vérification, bilan +
compte de résultat, export FEC, exercice/clôture annuelle, lettrage,
décimales.

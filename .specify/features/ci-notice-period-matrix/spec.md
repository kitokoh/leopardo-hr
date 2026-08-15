# Feature Specification: CI — préavis noticePeriodDays : matrice §8 par catégorie, palier 90 j supprimé

**Feature Branch**: `fix/2264-ci-notice-period-matrix`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2264

## Problème

`CedeaoPayrollRules::noticePeriodDays()` (CI) : `< 5 ans → 30 j ; 5-10 ans → 60 j ; ≥ 10 ans → 90 j`. Le palier 90 j pour ≥ 10 ans n'est documenté nulle part et contredit `docs/payroll/CI_COMPLIANCE.md §8` (Code du travail CI art. 18) : employés/techniciens `< 5 ans → 1 mois ; ≥ 5 ans → 2 mois` ; cadres → 3 mois. Le paramètre `category` existe dans l'interface depuis #2136/#2148 et `EndOfContractService` le transmet déjà (`employees.ipres_category`).

## User Stories & Testing

### User Story 1 — Matrice art. 18 par catégorie (P2)
**Acceptance Scenarios**:
1. Given un employé `cadre`, When `noticePeriodDays(years, 'cadre')`, Then 90 j quelle que soit l'ancienneté.
2. Given un employé `ouvrier`/`worker`, When `noticePeriodDays(years, 'ouvrier')`, Then 8 j (< 5 ans) / 15 j (≥ 5 ans).
3. Given un employé/technicien (défaut ou 'general'), When `noticePeriodDays(years)`, Then 30 j (< 5 ans) / 60 j (≥ 5 ans) — le palier 90 j non documenté disparaît.

### User Story 2 — Docs & tests alignés (P2)
**Acceptance Scenarios**:
1. Given CI_COMPLIANCE.md §8, When lecture, Then la matrice par catégorie est documentée et le ⚠️ « catégorie pas prise en compte » est levé.
2. Given les golden/unit tests CI, When exécution, Then `GoldenCiPayrollTest` (niveau employé 30/60 + matrice catégorie) et `CedeaoRulesUnitTest` verts.

## Plan technique
1. `CedeaoPayrollRules::noticePeriodDays()` : matrice par catégorie (cadre 90 ; ouvrier 8/15 ; défaut 30/60), docblock §8.
2. `GoldenCiPayrollTest` : provider employé réaligné (12 ans → 60) + nouveau test matrice catégorie (5 cas).
3. `CedeaoRulesUnitTest` : test `test_ci_notice_period_par_categorie`.
4. `CI_COMPLIANCE.md` §8 + tableau statut : implémentation par catégorie, palier 90 j supprimé.
5. CHANGELOG + PR `fix/2264-...` `Closes #2264`, CI verte.

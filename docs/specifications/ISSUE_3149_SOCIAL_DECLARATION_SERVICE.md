# Spec — Extraction `SocialDeclarationService` (déduplication #3149)

**Issue** : #3149 | **Statut** : Implémenté | **Date** : 2026-08-15

## Problème

`SocialDeclarationController` (556 l.) réimplémentait la même extraction
employés/bulletins/payroll_runs inline dans chaque méthode `generate*`
(3 agrégations pay_slips + 7 blocs de gardes tenant/RBAC/pays/audit quasi
identiques) — risque de divergence entre pays (ARCHITECTURE.md:162
« contrôleurs trop épais »).

## Correctif

1. Nouveau `SocialDeclarationService` (Payroll/Application/Services) :
   - `activeEmployees(companyId)` — employés actifs scopés tenant
   - `quarterMonths(quarter)` — Q1..Q4
   - `quarterPayrollData(companyId, year, months, withMonthsCount)` —
     agrégation trimestrielle des bulletins validés (CNAS DZ / CNSS MA)
   - `monthPayrollData(companyId, year, month)` — agrégation mensuelle
     brute+net (DSN FR)
2. Controller : les 3 méthodes JSON consomment le service ; helper privé
   `assertPayrollRunAccess()` (404 tenant → 403 RBAC → 422 pays → audit)
   pour les 7 déclarations par run (CI/SN `isManager`, CM/GA/CG/BF/ML
   `principal/comptable`).

## Critères d'acceptation

1. `SocialDeclarationControllerTest` (5 tests Feature, isolation cross-tenant) vert.
2. PHPStan strict level 8 : 0 erreur.
3. `SocialDeclarationGeneratorTest` (Unit) vert.
4. Aucun changement de payload/format (comportement identique).

# Feature Specification: Récapitulatif préavis par pays (issue #5325, gap G2)

**Feature Branch**: `mod/hr/5325-departure-notice`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5325 [P2][HR] — « Récapitulatif préavis — calcul par pays + suivi servi/non servi (G2) », source spec `.specify/features/hr-lifecycle/spec.md` (#5258).

## Problème

Le préavis légal est calculé côté Payroll (`CountryRulesInterface::noticePeriodDays()`, ex. DZ `AlgeriaPayrollRules` : 22 j ouvrés < 10 ans, 44 j ≥ 10 ans — loi 90-11 art. 73-4/98) mais HR n'a **aucun récapitulatif** : le manager ne voit ni la durée légale attendue (par pays/ancienneté) ni le statut de service du préavis.

## Décision

Endpoint HR **en lecture seule** (jamais de recalcul côté HR — la règle Payroll est la source de vérité) :

- `GET /api/v1/employees/{employee}/departure/notice` (RBAC manager, isolation tenant 404) :
  - `country_code` — pays de l'entreprise de l'employé ;
  - `years_of_service` — ancienneté calculée comme le moteur Payroll (`EndOfContractService::yearsOfService` : `contract_start->diffInMonths(endDate) / 12`, mois entiers) ;
  - `notice_days` — `CountryRulesResolver::resolve(country, companyId)->noticePeriodDays(yearsOfService)` (jours ouvrés) ;
  - `notice_status` — `served` / `not_served` / `unknown` : lecture résiliente de `employee_departures.notice_served` quand la table existe (workflow #5324), sinon `unknown` (fail-open documenté) ;
  - `notice_days_served` — jours effectivement servis si enregistrés ;
  - `rule_reference` + `confidence_level` — traçabilité légale (règle pays).

**Périmètre** : module HR uniquement (`api/app/Modules/HR/**` + routes + lang + tests). Controller/service **dédiés** (`DepartureNoticeController` / `DepartureNoticeService`) — aucun changement sur `DepartureService`/`EmployeeService`/`EmployeeDeparture` (workflow #5324 en cours sur une branche parallèle). Route `/departure/notice` distincte de `/departure` (#5324).

## User Scenarios & Testing

### User Story 1 — Le manager voit le préavis légal par pays/ancienneté (Priority: P2)

**Independent Test**: `php artisan test --filter=DepartureNoticeTest` (CI PostgreSQL 16) + golden de référence cohérent avec `EndOfContractService`.

**Acceptance Scenarios**:

1. **Given** une entreprise DZ et un employé avec 5 ans d'ancienneté, **When** le manager appelle `GET /employees/{id}/departure/notice`, **Then** `notice_days = 22` (loi 90-11 : 1 mois ≈ 22 j ouvrés).
2. **Given** un employé DZ avec 12 ans d'ancienneté, **Then** `notice_days = 44` (2 mois).
3. **Given** un pays sans règle de préavis (défaut `AbstractCountryRules`), **Then** `notice_days = 0` (aucun préavis légal par défaut — le contrat décide).
4. **Given** un employé d'une autre entreprise, **Then** 404 (isolation tenant).
5. **Given** un employé non-manager, **Then** 403 (RBAC).
6. **Given** un départ enregistré (`employee_departures.notice_served`), **Then** `notice_status = served/not_served` + `notice_days_served` ; sans table → `unknown` (résilient avant le merge #5324).

## Changement

- `api/app/Modules/HR/Infrastructure/Services/DepartureNoticeService.php` (neuf) : calcul du récapitulatif (règle Payroll consommée, jamais recalculée).
- `api/app/Modules/HR/Interfaces/Api/V1/Controllers/DepartureNoticeController.php` (neuf) : `show()` — RBAC manager, isolation tenant, audit `hr.departure_notice_viewed`.
- `api/routes/modules/rh.php` : route `GET /employees/{employee}/departure/notice`.
- `api/lang/{fr,en,tr,ar}/employees.php` : clés i18n ×4 (parité).
- `api/tests/Feature/HR/DepartureNoticeTest.php` (neuf) : golden DZ 22/44, pays sans règle → 0, RBAC, isolation, statut servi/résilient.
- `api/openapi.yaml` + miroir/SDK (`generate-openapi-sdk.mjs`) ; matrice frontend/API ; `docs/GUIDES/GUIDE_RH.md` ; CHANGELOG.

## Hors périmètre

- Le workflow de départ lui-même (enregistrement, SdC, attestation) = #5324 (branche parallèle).
- Le self-service employé (`/me/departure/notice`) = G5 (#5328, historique unifié).
- L'indemnité compensatrice de préavis (calcul Payroll `EndOfContractService`) — inchangée.
- Le gap G6 (exclusion des runs de paie pour les départs) = Payroll.

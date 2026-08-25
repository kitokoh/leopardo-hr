# Tasks: Congés légaux par pays — DZ / MA / TN / SN (issue #5289)

**Input**: spec.md + plan.md — `.specify/features/5289-legal-leaves-by-country/`

## T1 [P] [US4] Registre de règles légales
- Créer `api/app/Modules/Planning/Infrastructure/Services/CountryRules/LegalLeaveCountryRuleInterface.php`
- Créer `api/app/Modules/Planning/Infrastructure/Services/CountryRules/AbstractLegalLeaveCountryRule.php`
- Créer `AlgeriaLegalLeaveRule.php` (DZ : 30 j/an, 2,5 j/mois, source Loi 90-11 art. 14)
- Créer `MoroccoLegalLeaveRule.php` (MA : 24 j/an, 2 j/mois, source C. trav. art. 231)
- Créer `TunisiaLegalLeaveRule.php` (TN : 30 j/an, 2,5 j/mois, source convention 1966)
- Créer `SenegalLegalLeaveRule.php` (SN : 26 j/an, 2,17 j/mois, source C. trav. SN)
- Créer `LegalLeaveRulesRegistry.php` + `Exceptions/UnsupportedLeaveCountryException.php`
- Chaque règle porte la référence légale en commentaire + `confidenceLevel: 'pilot'`

## T2 [P] [US2] Services applicatifs
- Créer `LegalLeaveRulesService.php` : `resolve(company)`, `monthlyAccrualFor(company)` (cache), `floorAccrualFor(policy, company)`
- Créer `LegalLeaveEntitlementService.php` : `projectedEntitlement(employee, year)` — mois entiers depuis `contract_start`, plafond droit annuel

## T3 [P] [US3] Calendrier fériés légal
- Créer `LegalLeaveCalendarService.php` : `legalHolidays(countryCode, year)` — lecture seule `public_holidays` (nationaux + récurrents, cf. #1936)

## T4 [P] [US1] Intégration `leave:accrue`
- Modifier `api/app/Console/Commands/AccrueLeaveBalances.php` :
  - résoudre le plancher légal mensuel du pays de l'entreprise (via T2)
  - si politique `accrual_type=monthly` + `deducts_leave=true` + plancher > accrual configuré → acquérir le plancher
  - log `planning.legal_leave.floor_applied` (entreprise, politique, pays, ancien/nouveau montant)
  - description `LeaveAccrual` mentionnant la règle légale

## T5 [P] [US1-US4] Tests golden
- `api/tests/Unit/LegalLeaveEntitlementServiceTest.php` : DZ 10 mois → 25 j ; 24+ mois → plafond 30 ; pays non supporté → exception ; MA/TN/SN annuel
- `api/tests/Unit/LegalLeaveCalendarServiceTest.php` : DZ 2026 ≥ 4 fériés fixes ; année vide → []
- `api/tests/Feature/Leave/LegalLeaveAccrualTest.php` : DZ sous-légal → solde +2,5 ; ≥ plancher → inchangé ; pays non supporté → inchangé ; log émis
- `api/tests/Feature/Leave/LegalLeaveRulesRegistryTest.php` : résolution 4 pays + exception
- Vérifier 0 régression : `tests/Feature/Leave`, `tests/Feature/Absences`, `tests/Unit/AccrueLeaveBalancesTest`

## T6 [P] [US4] Docs + livraison
- Créer `docs/payroll/LEGAL_LEAVES.md` (inventaire DZ/MA/TN/SN : acquisition, plafonds, report, monétisation, sources)
- `api/CHANGELOG.md` : UNE ligne en tête d'[Unreleased] avec `Closes #5289`
- PR `mod/planning/5289-legal-leaves-by-country` → `main` ; body avec `Closes #5289`

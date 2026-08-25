# Plan: Congés légaux par pays — DZ / MA / TN / SN (issue #5289)

**Input**: spec.md — `.specify/features/5289-legal-leaves-by-country/spec.md`

## Décisions d'architecture

1. **Module d'accueil : `Planning`** (le moteur de congés y vit déjà :
   `LeavePolicy`, `LeaveBalance`, `LeaveAccrual`, `AbsenceType`, commandes).
   L'anti-collision de l'issue cite Absence/HR, mais le moteur réel est dans
   Planning — module **libre** (aucune branche `mod/planning/*`). Payroll reste
   **intouché** (lecture seule de `public_holidays`).
2. **Pattern miroir de Payroll `CountryRules`** : `app/Modules/Planning/Infrastructure/Services/CountryRules/`
   (cohérent avec `AbstractCountryRules` + classes par pays de Payroll).
3. **Pas de fallback silencieux** (constitution + MULTI_PAYS_RULES_ENGINE.md) :
   pays non supporté → `UnsupportedLeaveCountryException`.
4. **Plancher légal appliqué uniquement** aux politiques : `accrual_type = monthly`
   ET absence type `deducts_leave = true` ET pays supporté. Jamais moins que le minimum légal.
5. **Aucune route/API nouvelle** → pas de modification d'`openapi.yaml` (garde 744/744 conservée).
6. **Report** : l'enforcement des plafonds de report légaux reste à la politique
   entreprise (hors périmètre) ; le registre documente les règles.

## Arborescence cible

```
api/app/Modules/Planning/Infrastructure/Services/
├── CountryRules/
│   ├── LegalLeaveCountryRuleInterface.php
│   ├── AbstractLegalLeaveCountryRule.php
│   ├── AlgeriaLegalLeaveRule.php          (DZ — Loi 90-11 art. 14 : 30 j/an, 2,5 j/mois)
│   ├── MoroccoLegalLeaveRule.php          (MA — C. trav. art. 231 : 24 j/an, 2 j/mois)
│   ├── TunisiaLegalLeaveRule.php          (TN — conv. 1966 : 30 j/an, 2,5 j/mois)
│   ├── SenegalLegalLeaveRule.php          (SN — C. trav. : 26 j/an, 2,17 j/mois)
│   ├── LegalLeaveRulesRegistry.php        (pays → classe ; exception si non supporté)
│   └── Exceptions/UnsupportedLeaveCountryException.php
├── LegalLeaveRulesService.php             (resolve(company), legalMonthlyAccrual(company), floorFor(policy, company))
├── LegalLeaveEntitlementService.php       (projectedEntitlement(employee, policy?, year) — pur)
└── LegalLeaveCalendarService.php          (legalHolidays(country, year) — lecture public_holidays)
```

## Fichiers modifiés

- `api/app/Console/Commands/AccrueLeaveBalances.php` — application du plancher légal
  (via `LegalLeaveRulesService::monthlyAccrualFor`) + log `planning.legal_leave.floor_applied`.
- `api/CHANGELOG.md` — une ligne en tête d'[Unreleased].
- `api/docs` n'existe pas → `docs/payroll/LEGAL_LEAVES.md` (inventaire légal) à la racine docs/.

## Fichiers de test

- `api/tests/Unit/LegalLeaveEntitlementServiceTest.php` — calcul pur (prorata, plafond, exception).
- `api/tests/Unit/LegalLeaveCalendarServiceTest.php` — fériés légaux par pays (seed, récurrents, année vide).
- `api/tests/Feature/Leave/LegalLeaveAccrualTest.php` — plancher via `leave:accrue --force`
  (DZ sous-légal → 2,5 ; DZ ≥ plancher → inchangé ; pays non supporté → comportement historique).
- `api/tests/Feature/Leave/LegalLeaveRulesRegistryTest.php` — résolution des 4 pays + exception.

## Séquencement

1. Registre + interface + exceptions (US4)
2. `LegalLeaveRulesService` + `LegalLeaveEntitlementService` (US2)
3. `LegalLeaveCalendarService` (US3)
4. Intégration `leave:accrue` (US1)
5. Tests golden (US1-US4) + run complet des tests Leave/Absences existants (0 régression)
6. Docs + CHANGELOG + PR (`Closes #5289`)

# Feature Specification: Congés payés + absences → paie DZ (issue #5245)

**Feature Branch**: `mod/payroll/5245-leave-to-payroll`

**Created**: 2026-08-23

**Status**: Implémentée (PR en cours)

**Sources** : `docs/payroll/DZ_COMPLIANCE.md` §5-§6, `PayrollCalculator.php`
(F-05 #1816 / F-07 #1537 / F-20 #1919), issue #5245 (Programme 100 %, wave W1).

---

## 1. Contexte

Le run de paie doit intégrer automatiquement : jours d'absence non payés,
congés payés pris, jours fériés payés — et le détail par employé doit être
visible dans la simulation du run, pour qu'un comptable puisse vérifier le
calcul à la main.

**État avant** (vérifié sur main 2026-08-23) :

| Élément | Avant #5245 |
|---|---|
| Lecture des absences approuvées (payées/non payées) dans le calcul | ✅ `collectWorkInputs()` / `aggregateWorkInputs()` |
| Règles de proratisation (mois incomplet, congés sans solde) | ✅ R14, golden F-05 |
| Indemnité congés payés (maintien vs 1/10ᵉ) | ✅ F-07 |
| Jours fériés payés (exclus des jours ouvrés) | ✅ implicite (`workingDaysBetween`) |
| **Snapshot des entrées sur le bulletin** | ❌ non persisté |
| **Détail par employé dans la simulation du run** | ❌ non exposé par l'API |
| **DoD : run pilote = calcul manuel du comptable** | ❌ aucun test |

## 2. Changements

### 2.1 Migration tenant additive
`pay_slips` + `paid_leave_days` / `unpaid_leave_days` / `public_holiday_days`
(decimal(6,2), défaut 0). Snapshot rempli au calculate, comme
`working_days`/`actual_days_worked`. Rétro-compatible : vieux bulletins = 0.

### 2.2 PayrollCalculator
- `computeSlipValues()` retourne `paid_leave_days`, `unpaid_leave_days`
  (déjà calculés) + `public_holiday_days` (fériés dans la période via
  `PublicHolidayService::getHolidays`).
- `calculateSlip()` et le run de régularisation persistent les 3 colonnes.
- **Aucune règle de taux modifiée** (procédure #5149 non déclenchée).

### 2.3 API (affichage simulation du run)
- `PaySlipResource` : bloc `attendance` ADDITIF —
  `working_days, actual_days_worked, prorata_rate, paid_leave_days,
  unpaid_leave_days, public_holiday_days, overtime_hours,
  has_attendance_data, leave_balance`.
- `PaySlipController::index` / `indexForRun` : `leave_balance`
  {acquired, used, pending, remaining} agrégé par employé (types payés,
  année du bulletin) en **1 requête groupée** (zéro N+1). Non attaché sur
  `show` → `null` (aucune requête ajoutée).

### 2.4 Tests
- `GoldenDzLeaveToPayrollTest` (DB) — DoD : run mars 2026 (22 j ouvrés :
  31 − 8 repos ven/sam − 1 férié entreprise), 2 j congé payé + 3 j sans
  solde approuvés → base 46 363,64, indemnité 5 454,55, brut 51 818,19,
  net 42 122,82 (calcul manuel complet) ; garde F-20 anti double déduction
  (logs + congés) ; maintien de salaire (20/22 + indemnité 2/22 = brut complet).
- `PayrollLeaveIntegrationApiTest` — exposition API du détail + soldes,
  isolation tenant (404 cross-tenant), rétro-compatibilité `show`.

## 3. Contrat API (bloc ajouté, additif)

```json
"attendance": {
  "working_days": 22.0,
  "actual_days_worked": 20.0,
  "prorata_rate": 0.9091,
  "paid_leave_days": 2.0,
  "unpaid_leave_days": 0.0,
  "public_holiday_days": 1.0,
  "overtime_hours": 0.0,
  "has_attendance_data": false,
  "leave_balance": { "acquired": 30.0, "used": 2.0, "pending": 1.0, "remaining": 27.0 }
}
```

## 4. DoD

- [x] Un run avec congés/absences/fériés reproduit exactement le calcul
      manuel d'un comptable (test d'intégration, valeurs §2.4).
- [x] Détail par employé visible dans la simulation du run (bloc
      `attendance` + soldes).
- [x] Isolation tenant préservée (404 cross-tenant).
- [x] PHPStan strict level 8 vert, Pint vert, tests verts.

---
*Spec — issue #5245 (Programme 100 %, wave W1 Payroll DZ). Source de
vérité : `docs/payroll/DZ_COMPLIANCE.md` + `CHANGELOG.md`.*

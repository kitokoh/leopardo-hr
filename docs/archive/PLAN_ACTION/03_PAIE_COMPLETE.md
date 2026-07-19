# 03 — PAIE COMPLETE MULTI-PAYS

**Objectif :** Implementer une paie legale fonctionnelle pour DZ, MA, TN, FR, TR, SN — avec bulletins, cotisations, exports bancaires.

---

## 1. Architecture Paie

### Principe

La paie est un moteur de calcul configurable par pays. Chaque pays a ses regles de cotisations, tranches d'impots, et formats d'export. Le moteur est le meme, la configuration change.

```
PayrollEngine
  ├── CountryConfig (DZ, MA, TN, FR, TR, SN)
  │     ├── TaxSlabs (tranches impot sur revenu)
  │     ├── SocialContributions (CNAS, CNSS, CAVIS, etc.)
  │     ├── MinimumWage
  │     └── LegalConstraints
  ├── SalaryStructure (template par poste/grade)
  ├── SalaryComponents (composants: base, transport, prime, etc.)
  ├── PayrollRun (execution mensuelle)
  ├── PaySlip (bulletin individuel)
  └── BankExport (virement SEPA, CCP, etc.)
```

### Structure DDD

```
api/app/Modules/Payroll/
    Domain/
        Models/
            SalaryStructure.php
            SalaryComponent.php
            PayrollRun.php
            PaySlip.php
            PaySlipLine.php
            TaxSlab.php
            SocialContribution.php
            BankExport.php
        Enums/
            PayrollRunStatus.php    # draft, calculating, calculated, validated, paid, cancelled
            ComponentType.php       # earning, deduction, employer_contribution
            ExportFormat.php        # sepa_xml, ccp_dz, virement_ma, csv
        Events/
            PayrollRunValidated.php
            PaySlipGenerated.php
            BankExportGenerated.php
        Exceptions/
            PayrollAlreadyValidated.php
            EmployeeMissingSalaryStructure.php
    Application/
        Actions/
            CreatePayrollRunAction.php
            CalculatePayrollAction.php
            ValidatePayrollRunAction.php
            GeneratePaySlipsAction.php
            GenerateBankExportAction.php
        DTOs/
            PayrollRunDTO.php
        Queries/
            PayrollSummaryQuery.php
    Infrastructure/
        Services/
            PayrollCalculator.php
            CountryRules/
                AlgeriaPayrollRules.php
                MoroccoPayrollRules.php
                TunisiaPayrollRules.php
                FrancePayrollRules.php
                TurkeyPayrollRules.php
                SenegalPayrollRules.php
            Exports/
                SepaExporter.php
                CCPAlgeriaExporter.php
                CSVExporter.php
                PaySlipPdfGenerator.php
    Interfaces/
        Api/V1/
            Controllers/
                PayrollRunController.php
                PaySlipController.php
                SalaryStructureController.php
                SalaryComponentController.php
                TaxSlabController.php
                BankExportController.php
            Requests/
            Resources/
```

---

## 2. Modeles detailles

### SalaryStructure

```
SalaryStructure
  - id, company_id
  - name (ex: "Grille Ouvriers", "Grille Cadres")
  - base_salary (decimal)
  - currency (default: company currency)
  - country_code (DZ, MA, TN, FR, TR, SN)
  - frequency (enum: monthly, bi_weekly, weekly)
  - active (boolean)
  - created_at, updated_at
```

### SalaryComponent

```
SalaryComponent
  - id, company_id
  - salary_structure_id (nullable — global si null)
  - name (ex: "Salaire de base", "Prime transport", "CNAS salariale")
  - code (unique per company: BASE, TRANSPORT, CNAS_EMP, IRG, etc.)
  - type (enum: earning, deduction, employer_contribution)
  - calculation_type (enum: fixed, percentage_of_base, percentage_of_gross, formula)
  - amount (decimal, nullable — pour fixed)
  - percentage (decimal, nullable — pour percentage_*)
  - formula (string, nullable — ex: "max(0, gross - 30000) * 0.1")
  - is_taxable (boolean)
  - is_recurring (boolean)
  - order (integer — ordre de calcul)
  - active (boolean)
  - created_at, updated_at
```

### PayrollRun

```
PayrollRun
  - id, company_id
  - period_start, period_end
  - country_code
  - status (enum: draft, calculating, calculated, validated, paid, cancelled)
  - total_gross (decimal)
  - total_deductions (decimal)
  - total_net (decimal)
  - total_employer_cost (decimal)
  - employee_count (integer)
  - calculated_at (timestamp, nullable)
  - validated_by (user_id, nullable)
  - validated_at (timestamp, nullable)
  - paid_at (timestamp, nullable)
  - notes (text, nullable)
  - created_at, updated_at
```

### PaySlip

```
PaySlip
  - id, payroll_run_id, company_id, employee_id
  - contract_id (nullable)
  - period_start, period_end
  - gross_salary (decimal)
  - total_deductions (decimal)
  - net_salary (decimal)
  - employer_contributions (decimal)
  - total_cost (decimal)
  - working_days (decimal)
  - actual_days_worked (decimal)
  - overtime_hours (decimal)
  - status (enum: draft, calculated, validated, sent)
  - pdf_path (string, nullable)
  - sent_at (timestamp, nullable)
  - created_at, updated_at
```

### PaySlipLine

```
PaySlipLine
  - id, pay_slip_id
  - salary_component_id
  - name (string — snapshot du composant)
  - type (enum: earning, deduction, employer_contribution)
  - base_amount (decimal — montant de reference)
  - rate (decimal, nullable)
  - amount (decimal — montant calcule)
  - order (integer)
  - created_at
```

### TaxSlab

```
TaxSlab
  - id, company_id (nullable — global si null)
  - country_code
  - name (ex: "IRG 2026", "IR Maroc 2026")
  - min_amount, max_amount (decimal)
  - rate (decimal — pourcentage)
  - fixed_deduction (decimal, default 0)
  - effective_from (date)
  - effective_to (date, nullable)
  - created_at, updated_at
```

### SocialContribution

```
SocialContribution
  - id, company_id (nullable — global)
  - country_code
  - name (ex: "CNAS Salariale", "CNAS Patronale", "CNSS", "CAVIS")
  - code (unique)
  - type (enum: employee, employer)
  - rate (decimal — pourcentage du brut)
  - cap (decimal, nullable — plafond)
  - effective_from, effective_to
  - created_at, updated_at
```

### BankExport

```
BankExport
  - id, payroll_run_id, company_id
  - format (enum: sepa_xml, ccp_dz, virement_ma, csv_generic)
  - file_path (string)
  - total_amount (decimal)
  - transfer_count (integer)
  - status (enum: generated, sent, confirmed)
  - generated_at, sent_at
  - created_at, updated_at
```

---

## 3. Endpoints API

```
# Salary Structures
GET    /api/v1/salary-structures                  # Liste
POST   /api/v1/salary-structures                  # Creer
GET    /api/v1/salary-structures/{id}             # Detail
PUT    /api/v1/salary-structures/{id}             # Modifier
DELETE /api/v1/salary-structures/{id}             # Supprimer

# Salary Components
GET    /api/v1/salary-components                  # Liste
POST   /api/v1/salary-components                  # Creer
GET    /api/v1/salary-components/{id}             # Detail
PUT    /api/v1/salary-components/{id}             # Modifier
DELETE /api/v1/salary-components/{id}             # Supprimer

# Tax Slabs
GET    /api/v1/tax-slabs                          # Liste par pays
POST   /api/v1/tax-slabs                          # Creer
PUT    /api/v1/tax-slabs/{id}                     # Modifier
DELETE /api/v1/tax-slabs/{id}                     # Supprimer

# Social Contributions
GET    /api/v1/social-contributions               # Liste par pays
POST   /api/v1/social-contributions               # Creer
PUT    /api/v1/social-contributions/{id}          # Modifier
DELETE /api/v1/social-contributions/{id}          # Supprimer

# Payroll Runs
GET    /api/v1/payroll-runs                       # Liste
POST   /api/v1/payroll-runs                       # Creer (draft)
GET    /api/v1/payroll-runs/{id}                  # Detail
POST   /api/v1/payroll-runs/{id}/calculate        # Lancer le calcul
POST   /api/v1/payroll-runs/{id}/validate         # Valider
POST   /api/v1/payroll-runs/{id}/cancel           # Annuler
GET    /api/v1/payroll-runs/{id}/summary          # Resume global

# Pay Slips
GET    /api/v1/payroll-runs/{id}/pay-slips        # Bulletins d'un run
GET    /api/v1/pay-slips/{id}                     # Detail bulletin
GET    /api/v1/pay-slips/{id}/pdf                 # Telecharger PDF
POST   /api/v1/payroll-runs/{id}/send-slips       # Envoyer par email

GET    /api/v1/me/pay-slips                       # Mes bulletins (self-service)
GET    /api/v1/me/pay-slips/{id}/pdf              # Mon bulletin PDF

# Bank Exports
POST   /api/v1/payroll-runs/{id}/bank-export      # Generer fichier virement
GET    /api/v1/bank-exports/{id}                   # Detail
GET    /api/v1/bank-exports/{id}/download          # Telecharger fichier
```

---

## 4. Regles par pays

### Algerie (DZ)

```
Cotisations sociales :
  - CNAS salariale : 9% du brut
  - CNAS patronale : 26% du brut (dont AT 1.25%)
  - Retraite salariale : incluse dans les 9%

IRG (Impot sur le Revenu Global) :
  - 0-20 000 DZD/mois : 0%
  - 20 001-40 000 : 23%
  - 40 001-80 000 : 27%
  - 80 001-160 000 : 30%
  - 160 001-320 000 : 33%
  - >320 000 : 35%
  (Abattement de 40%, min 12 000 DZD/an, max 18 000 DZD/an)

SNMG : 20 000 DZD/mois
Monnaie : DZD

Export bancaire : Format CCP Algerie Poste ou virement bancaire local
```

### Maroc (MA)

```
Cotisations sociales :
  - CNSS salariale : 4.48% (plafond 6 000 MAD/mois)
  - CNSS patronale : 8.98% (plafond 6 000 MAD/mois)
  - AMO salariale : 2.26%
  - AMO patronale : 4.11%

IR (Impot sur le Revenu) :
  - 0-30 000 MAD/an : 0%
  - 30 001-50 000 : 10%
  - 50 001-60 000 : 20%
  - 60 001-80 000 : 30%
  - 80 001-180 000 : 34%
  - >180 000 : 38%

SMIG : 3 111 MAD/mois (14 ans)
Monnaie : MAD

Export : Virement bancaire standard
```

### Tunisie (TN)

```
Cotisations :
  - CNSS salariale : 9.18%
  - CNSS patronale : 16.57%

IRPP :
  - 0-5 000 TND/an : 0%
  - 5 001-20 000 : 26%
  - 20 001-30 000 : 28%
  - 30 001-50 000 : 32%
  - >50 000 : 35%

SMIG : 480 TND/mois
Monnaie : TND
```

### France (FR)

```
Cotisations (simplifiees) :
  - Securite sociale salariale : ~7.5% environ
  - Securite sociale patronale : ~30% environ
  - CSG/CRDS : 9.2% / 0.5%
  - Retraite complementaire : variable

Impot a la source (PAS) :
  - Taux personnalise ou taux neutre selon grille

SMIC : ~1 766 EUR brut/mois
Monnaie : EUR

Export : SEPA XML (pain.001)
```

### Turquie (TR)

```
Cotisations :
  - SGK salariale : 14%
  - SGK patronale : 20.5%
  - Chomage salariale : 1%
  - Chomage patronale : 2%

Impot (Gelir Vergisi) :
  - 0-110 000 TRY/an : 15%
  - 110 001-230 000 : 20%
  - 230 001-580 000 : 27%
  - 580 001-3 000 000 : 35%
  - >3 000 000 : 40%

SMIC : 20 002 TRY brut/mois
Monnaie : TRY
```

### Senegal (SN)

```
Cotisations :
  - IPRES salariale : 5.6%
  - IPRES patronale : 8.4%
  - CSS patronale : 1-5% (accidents du travail)

Impot (IR) :
  - Bareme progressif, base annuelle

SMIG : 58 900 FCFA/mois
Monnaie : XOF
```

### Taches

- [x] **T-PAIE-01** : Creer les migrations pour les 7 nouvelles tables — **FAIT** (`2026_05_10_100001_create_payroll_engine_tables.php` : SalaryStructure, SalaryComponent, PayrollRun, PaySlip, PaySlipLine, TaxSlab, SocialContribution, BankExport)
- [x] **T-PAIE-02** : Creer les modeles Eloquent — **FAIT** (8 modeles dans `app/Models/` : PayrollRun, PaySlip, PaySlipLine, SalaryStructure, SalaryComponent, TaxSlab, SocialContribution, BankExport)
- [x] **T-PAIE-03** : Creer les seeders de configuration par defaut pour DZ, MA, TN, FR, TR, SN — **FAIT** (`database/seeders/PayrollCountryConfigSeeder.php`)
- [x] **T-PAIE-04** : Implementer `PayrollCalculator` avec le moteur de calcul — **FAIT** (`app/Services/Payroll/PayrollCalculator.php`)
- [x] **T-PAIE-05** : Implementer les 6 `CountryRules` classes — **FAIT** (Algeria, Morocco, Tunisia, France, Turkey, Senegal dans `app/Services/Payroll/CountryRules/` + AbstractCountryRules + CountryRulesInterface)
- [x] **T-PAIE-06** : Implementer `CalculatePayrollAction` — **FAIT** (logique dans `PayrollService.php` + `PayrollRunController`)
- [x] **T-PAIE-07** : Implementer `PaySlipPdfGenerator` (DomPDF, template bulletin) — **FAIT** (`app/Services/PaySlipPdfGenerator.php` + `resources/views/pdf/payslip.blade.php`)
- [x] **T-PAIE-08** : Implementer `SepaExporter` (format pain.001.003.03) — **FAIT** (dans `app/Services/BankExportGenerator.php` SEPA XML)
- [x] **T-PAIE-09** : Implementer `CCPAlgeriaExporter` (format CCP) — **FAIT** (dans `app/Services/BankExportGenerator.php` CCP format)
- [x] **T-PAIE-10** : Creer tous les endpoints API — **FAIT** (PayrollRunController, PaySlipController, SalaryStructureController, SalaryComponentController, TaxSlabController, BankExportController dans `routes/modules/payroll_engine.php`)
- [x] **T-PAIE-11** : Creer les FormRequests et API Resources — **FAIT** (StorePayrollRequest, UpdatePayrollRequest, PayrollIndexRequest, PayrollRunResource, PaySlipResource)
- [x] **T-PAIE-12** : Creer les Policies (manager + comptable only pour validation) — **FAIT** (`app/Policies/PayrollPolicy.php`)
- [x] **T-PAIE-13** : Tests Feature complets — **FAIT** (`tests/Feature/PayrollRunControllerTest.php`, `tests/Feature/PaySlipControllerTest.php`, `tests/Feature/PayrollCycleIntegrationTest.php`)
- [x] **T-PAIE-14** : Tests Unit pour chaque CountryRules (calculs exacts) — **FAIT** (`tests/Unit/PayrollCountryRulesTest.php`, `tests/Unit/BankExportGeneratorTest.php`)
- [x] **T-PAIE-15** : Self-service /me/pay-slips — **FAIT** (3 routes : `GET /me/pay-slips`, `GET /me/pay-slips/{id}`, `GET /me/pay-slips/{id}/pdf`)

---

## 5. Securite paie

- Les payroll runs valides sont **immutables** — aucune modification possible apres validation
- Les bulletins envoyes ne sont jamais supprimes, seulement archives
- L'acces aux bulletins est restreint : l'employe voit les siens, le manager voit son equipe, le comptable/RH voit tous
- Les montants sont stockes en centimes (integer) pour eviter les erreurs de virgule flottante
- Chaque validation de payroll declenche un audit log detaille

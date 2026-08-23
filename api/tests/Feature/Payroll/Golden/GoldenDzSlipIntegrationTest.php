<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Carbon\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5149 — golden tests « bulletin réel » (calculateRun) DZ.
 *
 * Complète GoldenDzFullSlipTest (flux pur) par des bulletins COMPLETS
 * générés via PayrollCalculator::calculateRun() : les montants du bulletin
 * (gross_salary, total_deductions, net_salary, employer_contributions,
 * total_cost) et de ses lignes sont calculés À LA MAIN et verrouillés —
 * référence docs/payroll/DZ_COMPLIANCE.md §1-§2-§5-§6.
 *
 * Trous de la matrice #5149 couverts ici :
 *  - prime fixe soumise (incluse dans le brut → CNAS + IRG) ;
 *  - heures supplémentaires issues du pointage (AttendanceLog) ;
 *  - indemnité de congés payés imposable (1/10ᵉ sur référence réelle 12 mois) ;
 *  - congés sans solde (absence déduite du prorata, aucune indemnité) ;
 *  - période partielle (entrée 15/07 → prorata 12,06/22).
 *
 * DB-backed (RefreshTenantDatabase) : ces scénarios exigent de vrais
 * modèles (structures, pointages, absences, historique de bulletins).
 */
class GoldenDzSlipIntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function makeStructure(Company $company, float $base): SalaryStructure
    {
        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille Golden #5149 — '.$base,
            'base_salary' => $base,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        return $structure;
    }

    private function makeEmployee(Company $company, ?int $structureId = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_start' => '2026-01-01',
            'salary_structure_id' => $structureId,
        ]);

        return $employee;
    }

    private function makeRun(Company $company): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        return $run;
    }

    public function test_golden_dz_run_prime_fixe_soumise_10000(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §1-§2) — brut = base 60 000 + prime 10 000 :
        //   prime SOUmise : incluse dans le brut → assiette CNAS et IRG complète
        //   CNAS salariale = 70 000 × 9 % = 6 300 → assiette 63 700
        //   IRG(63 700) : 4 600 + 23 700×27 % = 10 999 → annuel 131 988
        //     → abattement plafonné 18 000 → IRG mensuel = (131 988 − 18 000)/12 = 9 499
        //   déductions = 6 300 + 9 499 = 15 799 → net = 70 000 − 15 799 = 54 201
        //   patronale = 18 200 → coût employeur = 88 200
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        SalaryComponent::create([
            'company_id' => $company->id,
            'salary_structure_id' => $structure->id,
            'name' => 'Prime de performance',
            'code' => 'PRIME_PERF_5149',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 10000.0,
            'percentage' => 0.0,
            'is_taxable' => true,
            'is_recurring' => true,
            'order' => 1,
            'active' => true,
        ]);
        $this->makeEmployee($company, $structure->id);

        $run = $this->makeRun($company);
        (new PayrollCalculator)->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(70000.0, (float) $slip->gross_salary);
        $this->assertSame(15799.0, (float) $slip->total_deductions);
        $this->assertSame(54201.0, (float) $slip->net_salary);
        $this->assertSame(18200.0, (float) $slip->employer_contributions);
        $this->assertSame(88200.0, (float) $slip->total_cost);

        $primeLine = $slip->lines->where('name', 'Prime de performance')->first();
        $this->assertNotNull($primeLine);
        $this->assertSame(10000.0, (float) $primeLine->amount);
    }

    public function test_golden_dz_run_heures_sup_10h_from_attendance(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5, F-20 + #5266) :
        //   22 jours pointés (mois complet) + 10 h sup sur un log
        //   HS = 10 × (60 000/173,33) × 1,50 = 5 192,41
        //     (palier unique ≥ 50 %, art. 32 loi 90-11 — écart E2 arbitré #5266)
        //   brut = 60 000 + 5 192,41 = 65 192,41
        //   CNAS salariale = 65 192,41 × 9 % = 5 867,32 → assiette 59 325,09
        //   IRG(59 325,09) : 4 600 + 19 325,09×27 % = 9 817,77
        //     → annuel 117 813,24 → abattement plafonné 18 000 → IRG mensuel 8 317,77
        //   net = 65 192,41 − 5 867,32 − 8 317,77 = 51 007,32
        //   patronale = 16 950,03 → coût employeur = 82 142,44
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $employee = $this->makeEmployee($company, $structure->id);

        // Jours ouvrés DZ de juillet 2026 (repos hebdo vendredi/samedi —
        // AlgeriaPayrollRules::weeklyRestDays [5, 6]) : 22 jours distincts.
        $workingDays = ['01', '02', '05', '06', '07', '08', '09', '12', '13', '14', '15', '16', '19', '20', '21', '22', '23', '26', '27', '28', '29', '30'];
        foreach ($workingDays as $day) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => "2026-07-{$day}",
                'status' => 'ontime',
                'overtime_hours' => $day === '08' ? 10.0 : 0.0,
            ]);
        }

        $run = $this->makeRun($company);
        (new PayrollCalculator)->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(22.0, (float) $slip->working_days);
        $this->assertSame(22.0, (float) $slip->actual_days_worked);
        $this->assertSame(10.0, (float) $slip->overtime_hours);
        $this->assertTrue((bool) $slip->has_attendance_data);

        $this->assertSame(65192.41, (float) $slip->gross_salary);
        $this->assertSame(51007.32, (float) $slip->net_salary);
        $this->assertSame(16950.03, (float) $slip->employer_contributions);
        $this->assertSame(82142.44, (float) $slip->total_cost);

        $hsLine = $slip->lines->where('name', 'Heures supplémentaires')->first();
        $this->assertNotNull($hsLine);
        $this->assertSame(5192.41, (float) $hsLine->amount);
    }

    public function test_golden_dz_run_indemnite_cp_imposable(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §6, F-07 #1537) :
        //   10 j de congé payé, base 60 000, référence réelle 12 mois = 1 200 000,
        //   acquis réels 30 j (balance 25 + used 5) :
        //     maintien = 60 000 × 10/22 = 27 272,73
        //     1/10ᵉ    = (1 200 000/10) × 10/30 = 40 000,00 ← retenu (plus favorable)
        //   Fallback sans logs : jours travaillés = 22 − 10 = 12
        //     → base proratisée = 60 000 × 12/22 = 32 727,27
        //   brut = 32 727,27 + 40 000 = 72 727,27 — l'indemnité CP est SOUmise :
        //   elle entre dans l'assiette CNAS et IRG (rémunération, CIDTA art. 104)
        //   CNAS salariale = 72 727,27 × 9 % = 6 545,45 → assiette 66 181,82
        //   IRG(66 181,82) : 4 600 + 26 181,82×27 % = 11 669,09
        //     → annuel 140 029,09 → abattement plafonné 18 000 → IRG mensuel 10 169,09
        //   net = 72 727,27 − 6 545,45 − 10 169,09 = 56 012,73
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $employee = $this->makeEmployee($company, $structure->id);

        /** @var AbsenceType $type */
        $type = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'PAID_LEAVE_5149',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
            'max_days_once' => 30,
        ]);
        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-17',
            'days_count' => 10,
            'status' => 'approved',
        ]);

        // Référence 12 mois réelle : 12 bulletins validés à 100 000 DZD
        // (2025-07 → 2026-06, les 12 mois précédant la période du run).
        for ($m = 0; $m < 12; $m++) {
            $ref = Carbon::parse('2025-07-01')->addMonths($m);
            /** @var PayrollRun $historyRun */
            $historyRun = PayrollRun::create([
                'company_id' => $company->id,
                'period_start' => $ref->format('Y-m-01'),
                'period_end' => $ref->endOfMonth()->toDateString(),
                'country_code' => 'DZ',
                'status' => 'validated',
            ]);
            PaySlip::create([
                'payroll_run_id' => $historyRun->id,
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'period_start' => $ref->format('Y-m-01'),
                'period_end' => $ref->endOfMonth()->toDateString(),
                'gross_salary' => 100000,
                'net_salary' => 78000,
                'status' => 'validated',
            ]);
        }

        // Jours acquis réels : 30 (balance restante 25 + 5 déjà pris).
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 25,
            'used' => 5,
            'pending' => 0,
            'year' => 2026,
        ]);

        $run = $this->makeRun($company);
        (new PayrollCalculator)->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $baseLine = $slip->lines->where('name', 'Salaire de base')->first();
        $indemnityLine = $slip->lines->where('name', 'Indemnité de congés payés')->first();
        $this->assertNotNull($baseLine);
        $this->assertNotNull($indemnityLine);
        $this->assertSame(32727.27, (float) $baseLine->amount);
        $this->assertSame(40000.0, (float) $indemnityLine->amount);

        $this->assertSame(72727.27, (float) $slip->gross_salary);
        $this->assertSame(56012.73, (float) $slip->net_salary);
        $this->assertSame(18909.09, (float) $slip->employer_contributions);
        $this->assertSame(91636.36, (float) $slip->total_cost);
    }

    public function test_golden_dz_run_conges_sans_solde_5j(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5 — congés sans solde déduits) :
        //   fallback sans logs : jours travaillés = 22 − 5 = 17
        //   base = 60 000 × 17/22 = 46 363,64 (retenue 13 636,36)
        //   aucune indemnité CP (absence NON payée)
        //   brut = 46 363,64 → CNAS salariale = 4 172,73 → assiette 42 190,91
        //   IRG(42 190,91) : 4 600 + 2 190,91×27 % = 5 191,55
        //     → annuel 62 298,55 → abattement plafonné 18 000 → IRG mensuel 3 691,55
        //   net = 46 363,64 − 4 172,73 − 3 691,55 = 38 499,36
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $employee = $this->makeEmployee($company, $structure->id);

        /** @var AbsenceType $type */
        $type = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Congé sans solde',
            'code' => 'UNPAID_LEAVE_5149',
            'is_paid' => false,
            'deducts_leave' => true,
            'requires_proof' => false,
            'max_days_once' => 30,
        ]);
        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-17',
            'days_count' => 5,
            'status' => 'approved',
        ]);

        $run = $this->makeRun($company);
        (new PayrollCalculator)->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(46363.64, (float) $slip->gross_salary);
        $this->assertSame(38499.36, (float) $slip->net_salary);
        $this->assertSame(12054.55, (float) $slip->employer_contributions);
        $this->assertSame(58418.19, (float) $slip->total_cost);

        // Aucune indemnité CP : l'absence n'est pas payée.
        $this->assertNull($slip->lines->where('name', 'Indemnité de congés payés')->first());
    }

    public function test_golden_dz_run_prorata_entree_15_07(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5 — entrée 15/07, fallback contrat) :
        //   chevauchement contrat ↔ période = 17 j calendaires sur 31
        //   jours travaillés = 22 × 17/31 = 12,06 j
        //   base = 60 000 × 12,06/22 = 32 890,91
        //   CNAS salariale = 2 960,18 → assiette 29 930,73
        //   IRG(29 930,73) : 9 930,73×23 % = 2 284,07 → annuel 27 408,81
        //     → abattement = plancher 12 000 (40 % = 10 963,53 < 12 000)
        //     → IRG mensuel = (27 408,81 − 12 000)/12 = 1 284,07
        //   net = 32 890,91 − 2 960,18 − 1 284,07 = 28 646,66
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_start' => '2026-07-15', // entrée en cours de mois
            'salary_structure_id' => $structure->id,
        ]);

        $run = $this->makeRun($company);
        (new PayrollCalculator)->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(12.06, (float) $slip->actual_days_worked);
        $this->assertFalse((bool) $slip->has_attendance_data);

        $this->assertSame(32890.91, (float) $slip->gross_salary);
        $this->assertSame(28646.66, (float) $slip->net_salary);
        $this->assertSame(8551.64, (float) $slip->employer_contributions);
        $this->assertSame(41442.55, (float) $slip->total_cost);
    }
}

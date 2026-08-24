<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5245 (Programme 100 %, wave W1) — intégration Attendance/Absence → paie DZ.
 *
 * DoD : « Un run pilote reproduit exactement le calcul manuel d'un comptable ».
 * Chaque valeur attendue est CALCULÉE À LA MAIN (docs/payroll/DZ_COMPLIANCE.md
 * §5-§6) : absences approuvées (congés payés pris, congés sans solde), jour
 * férié payé, prorata — jamais reprise du moteur.
 *
 * Scénario de référence (mars 2026 — calcul manuel) :
 *  - jours calendaires 31, repos DZ vendredi+samedi (loi 90-11 art. 27) = 8 j
 *  - 1 férié entreprise le 19/03 → jours ouvrés = 31 − 8 − 1 = 22
 *  - congé PAYÉ approuvé : 2 j (10-11/03) ; congé SANS SOLDE approuvé : 3 j (16-18/03)
 *  - aucun log de pointage → repli prorata : jours travaillés = 22 − 5 = 17
 *  - base proratisée = 60 000 × 17/22 = 46 363,64
 *  - indemnité congés payés = max(maintien 60 000 × 2/22 = 5 454,55 ;
 *    1/10ᵉ (720 000/10) × 2/30 = 4 800,00) = 5 454,55
 *  - brut = 46 363,64 + 5 454,55 = 51 818,19
 *  - CNAS salariale 9 % = 4 663,64 ; patronale 26 % = 13 472,73
 *  - assiette IRG = 51 818,19 − 4 663,64 = 47 154,55
 *  - IRG(47 154,55) : 4 600 + 7 154,55×27 % = 6 531,73 → annuel 78 380,76
 *    → abattement plafonné 18 000 → IRG mensuel = 5 031,73
 *  - net = 51 818,19 − 4 663,64 − 5 031,73 = 42 122,82
 *  - coût employeur = 51 818,19 + 13 472,73 = 65 290,92
 */
class GoldenDzLeaveToPayrollTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        // Employé actif, CDI depuis 2025 (mois complet couvert).
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'contract_type' => 'CDI',
            'contract_start' => '2025-01-01',
            'salary_base' => 60000,
            'salary_type' => 'fixed',
        ]);
        $this->employee = $employee;

        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille DZ 60k',
            'code' => 'GRID-DZ-60K',
            'base_salary' => 60000,
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);
    }

    private function createRun(): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);
    }

    private function createAbsenceType(bool $paid): AbsenceType
    {
        return AbsenceType::create([
            'company_id' => $this->company->id,
            'name' => $paid ? 'Congé payé' : 'Congé sans solde',
            'code' => $paid ? 'CP' : 'CSS',
            'is_paid' => $paid,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }

    /**
     * Férié ENTREPRISE dans la période (31 j calendaires, 8 repos
     * vendredi/samedi, 1 férié → 22 jours ouvrés pour mars 2026).
     */
    private function seedCompanyHoliday(): void
    {
        PublicHoliday::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Fête d\'entreprise (test)',
            'date' => '2026-03-19',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'company',
        ]);
    }

    private function approveAbsence(AbsenceType $type, string $start, string $end, int $days): void
    {
        Absence::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'days_count' => $days,
            'status' => 'approved',
            'reason' => 'Test #5245',
        ]);
    }

    public function test_dod_run_reproduces_manual_accountant_calculation_with_leaves_and_holiday(): void
    {
        $this->seedCompanyHoliday();

        $paidType = $this->createAbsenceType(true);
        $unpaidType = $this->createAbsenceType(false);

        $this->approveAbsence($paidType, '2026-03-10', '2026-03-11', 2);
        $this->approveAbsence($unpaidType, '2026-03-16', '2026-03-18', 3);

        $run = $this->createRun();
        $calculated = app(PayrollCalculator::class)->calculateRun($run);

        $this->assertSame(PayrollRun::STATUS_CALCULATED, $calculated->status);

        /** @var PaySlip $slip */
        $slip = $calculated->paySlips()->where('employee_id', $this->employee->id)->firstOrFail();

        // ── Détail des entrées de travail (le cœur de #5245) ───────────────
        $this->assertSame(22.0, $slip->working_days);            // 31 − 8 repos − 1 férié
        $this->assertSame(17.0, $slip->actual_days_worked);      // 22 − 2 CP − 3 CSS
        $this->assertSame(2.0, $slip->paid_leave_days);
        $this->assertSame(3.0, $slip->unpaid_leave_days);
        $this->assertSame(1.0, $slip->public_holiday_days);
        $this->assertFalse($slip->has_attendance_data);

        // ── Calcul manuel du comptable (DZ_COMPLIANCE.md §5-§6) ────────────
        /** @var PaySlipLine $baseLine */
        $baseLine = $slip->lines->firstWhere('name', 'Salaire de base');
        $this->assertSame(46363.64, $baseLine->amount);
        $this->assertSame(5454.55, $this->leaveIndemnityLine($slip));                         // maintien > 1/10ᵉ
        $this->assertSame(51818.19, $slip->gross_salary);

        // CNAS / IRG / net / coût — mêmes formules que les golden F-03/F-05.
        $rules = new AlgeriaPayrollRules;
        $charges = $rules->calculateSocialCharges($slip->gross_salary);
        $taxable = $slip->gross_salary - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);

        $this->assertSame(4663.64, $charges['employee']);
        $this->assertEqualsWithDelta(47154.55, $taxable, 0.0001);
        $this->assertSame(5031.73, $irg);
        $this->assertSame(42122.82, $slip->net_salary);
        $this->assertSame(13472.73, $charges['employer']);
        $this->assertSame(65290.92, $slip->total_cost);

        // Le bulletin porte bien les lignes attendues.
        /** @var PaySlipLine $baseLine */
        $baseLine = $slip->lines->firstWhere('name', 'Salaire de base');
        $this->assertSame(46363.64, $baseLine->amount);
        $this->assertSame(5454.55, $this->leaveIndemnityLine($slip));
    }

    /**
     * Garde F-20 (#1919) : avec des LOGS DE PRÉSENCE réels, les jours de congé
     * sont DÉJÀ exclus du décompte — re-soustraire les congés paierait deux
     * fois. 17 jours pointés + 2 j de congé payé → 17/22 payés, pas 15/22.
     */
    public function test_attendance_logs_do_not_double_deduct_paid_leave(): void
    {
        $this->seedCompanyHoliday();

        $paidType = $this->createAbsenceType(true);
        $this->approveAbsence($paidType, '2026-03-10', '2026-03-11', 2);

        // 17 jours de présence réelle (jours ouvrés hors 19/03 et hors congé).
        $workDays = ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05',
            '2026-03-08', '2026-03-09', '2026-03-12', '2026-03-15',
            '2026-03-22', '2026-03-23', '2026-03-24', '2026-03-25',
            '2026-03-26', '2026-03-29', '2026-03-30', '2026-03-31', '2026-03-01'];
        foreach ($workDays as $day) {
            AttendanceLog::create([
                'company_id' => $this->company->id,
                'employee_id' => $this->employee->id,
                'date' => $day,
                'session_number' => 1,
                'check_in' => $day.' 08:30:00',
                'check_out' => $day.' 17:30:00',
                'method' => 'mobile',
                'status' => 'ontime',
                'hours_worked' => 8.0,
                'overtime_hours' => 0,
                'late_minutes' => 0,
            ]);
        }

        $run = $this->createRun();
        $calculated = app(PayrollCalculator::class)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $calculated->paySlips()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertTrue($slip->has_attendance_data);
        $this->assertSame(22.0, $slip->working_days);
        $this->assertSame(17.0, $slip->actual_days_worked);   // 17 logs — PAS 17−2
        $this->assertSame(2.0, $slip->paid_leave_days);
        $this->assertSame(0.0, $slip->unpaid_leave_days);
        $this->assertSame(1.0, $slip->public_holiday_days);

        // 17/22 payés + indemnité CP 2 j = 5 454,55 → même brut que le DoD.
        $this->assertSame(51818.19, $slip->gross_salary);
    }

    /**
     * Présence quasi-complète (20 jours ouvrés pointés) + 2 j de congé payé :
     * le congé PAYÉ ne réduit pas la paie — le maintien de salaire (indemnité
     * CP) compense EXACTEMENT le prorata : base 60 000 × 20/22 = 54 545,45
     * + indemnité 5 454,55 → brut 60 000,00 (22/22 payés). C'est le calcul
     * qu'attend un comptable DZ (loi 90-11, congés payés = maintien).
     */
    public function test_full_attendance_with_paid_leave_keeps_full_pay(): void
    {
        $this->seedCompanyHoliday();

        $paidType = $this->createAbsenceType(true);
        $this->approveAbsence($paidType, '2026-03-10', '2026-03-11', 2);

        // 20 jours ouvrés pointés (tous les jours ouvrés de mars 2026 sauf le
        // férié 19/03 et les 2 jours de congé 10-11/03).
        $workDays = ['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04',
            '2026-03-05', '2026-03-08', '2026-03-09', '2026-03-12',
            '2026-03-15', '2026-03-16', '2026-03-17', '2026-03-18',
            '2026-03-22', '2026-03-23', '2026-03-24', '2026-03-25',
            '2026-03-26', '2026-03-29', '2026-03-30', '2026-03-31'];
        $this->assertCount(20, $workDays);

        foreach ($workDays as $day) {
            AttendanceLog::create([
                'company_id' => $this->company->id,
                'employee_id' => $this->employee->id,
                'date' => $day,
                'session_number' => 1,
                'check_in' => $day.' 08:30:00',
                'check_out' => $day.' 17:30:00',
                'method' => 'mobile',
                'status' => 'ontime',
                'hours_worked' => 8.0,
                'overtime_hours' => 0,
                'late_minutes' => 0,
            ]);
        }

        $run = $this->createRun();
        $calculated = app(PayrollCalculator::class)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $calculated->paySlips()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertSame(22.0, $slip->working_days);
        $this->assertSame(20.0, $slip->actual_days_worked);
        $this->assertSame(2.0, $slip->paid_leave_days);
        // Maintien de salaire : base 20/22 + indemnité CP 2/22 = brut complet.
        /** @var PaySlipLine $baseLine */
        $baseLine = $slip->lines->firstWhere('name', 'Salaire de base');
        /** @var PaySlipLine $cpLine */
        $cpLine = $slip->lines->firstWhere('name', 'Indemnité de congés payés');
        $this->assertSame(54545.45, $baseLine->amount);
        $this->assertSame(5454.55, $cpLine->amount);
        $this->assertSame(60000.0, $slip->gross_salary);
    }

    private function leaveIndemnityLine(PaySlip $slip): float
    {
        /** @var PaySlipLine $cpLine */
        $cpLine = $slip->lines->firstWhere('name', 'Indemnité de congés payés');

        return (float) $cpLine->amount;
    }
}

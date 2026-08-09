<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-07 (#1537) : l'indemnité de congés payés utilise les
 * données réelles (salaires bruts validés des 12 derniers mois + jours acquis
 * LeaveBalance) et non plus l'approximation base × 12 / 30 j.
 *
 * Scénario calculé à la main :
 *   base mensuelle 60 000 DZD · 10 jours de congé payé · 22 jours ouvrés
 *   référence 12 mois réelle : 12 bulletins validés à 100 000 → 1 200 000
 *   acquis réels : LeaveBalance = 25 j (au lieu du défaut 30)
 *
 *   maintien = 60 000 × 10 / 22          = 27 272,73
 *   1/10ᵉ    = (1 200 000 / 10) × 10/25  = 48 000,00  → retenu (plus favorable)
 *
 * Avec l'ancienne approximation (base×12 = 720 000 / acquis 30) :
 *   1/10ᵉ = 24 000 < maintien → 27 272,73 aurait été retenu à tort.
 */
class GoldenDzLeaveIndemnityRealDataTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_leave_indemnity_uses_real_reference_gross_and_accrued_days(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille Test F-07',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        // Type d'absence payé + absence approuvée de 10 jours dans la période.
        /** @var AbsenceType $type */
        $type = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'PAID_LEAVE',
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
        // (2025-07 → 2026-06, soit les 12 mois précédant la période du run).
        /** @var PayrollRun $historyRun */
        $historyRun = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2025-07-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        for ($m = 0; $m < 12; $m++) {
            $ref = \Carbon\Carbon::parse('2025-07-01')->addMonths($m);
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

        // Jours acquis réels : 25 (LeaveBalance, année de la période du run).
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'balance' => 25,
            'used' => 5,
            'pending' => 0,
            'year' => 2026,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        (new PayrollCalculator())->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $indemnityLine = $slip->lines->where('name', 'Indemnité de congés payés')->first();
        $this->assertNotNull($indemnityLine, 'La ligne indemnité de congés payés doit être présente.');
        $this->assertSame(48000.0, (float) $indemnityLine->amount);
    }

    public function test_leave_indemnity_falls_back_without_history(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille Test F-07 fallback',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var AbsenceType $type */
        $type = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'PAID_LEAVE',
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

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        (new PayrollCalculator())->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        // Fallback : référence = 60 000 × 12 = 720 000 · acquis = 30 j.
        //   maintien = 60 000 × 10/22 = 27 272,73
        //   1/10ᵉ    = (720 000/10) × 10/30 = 24 000  → maintien retenu.
        $indemnityLine = $slip->lines->where('name', 'Indemnité de congés payés')->first();
        $this->assertNotNull($indemnityLine);
        $this->assertSame(27272.73, (float) $indemnityLine->amount);
    }
}

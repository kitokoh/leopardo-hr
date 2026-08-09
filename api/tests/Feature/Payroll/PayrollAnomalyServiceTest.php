<?php

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\PayrollAnomalyService;
use Tests\TestCase;

/**
 * Programme FOCUS — F-28 : détection d'anomalies de paie (lecture seule,
 * action humaine requise — WriteToolPolicy : jamais d'écriture automatique).
 */
class PayrollAnomalyServiceTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    private function makeRun(Company $company, string $status = 'calculated', string $period = '2026-07'): PayrollRun
    {
        return PayrollRun::create([
            'company_id'    => $company->id,
            'period_start'  => "{$period}-01",
            'period_end'    => "{$period}-31",
            'country_code'  => 'DZ',
            'status'        => $status,
            'total_gross'   => 0,
            'total_net'     => 0,
            'employee_count'=> 0,
        ]);
    }

    private function makeSlip(PayrollRun $run, Employee $employee, float $gross, float $deductions, float $net): PaySlip
    {
        return PaySlip::create([
            'payroll_run_id'    => $run->id,
            'company_id'        => $run->company_id,
            'employee_id'       => $employee->id,
            'period_start'      => $run->period_start,
            'period_end'        => $run->period_end,
            'gross_salary'      => $gross,
            'total_deductions'  => $deductions,
            'net_salary'        => $net,
            'status'            => 'calculated',
        ]);
    }

    public function test_coherent_run_has_no_anomalies(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = $this->makeRun($company);
        $slip = $this->makeSlip($run, $employee, 60000.0, 13900.0, 46100.0);
        PaySlipLine::create(['pay_slip_id' => $slip->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 60000.0]);
        PaySlipLine::create(['pay_slip_id' => $slip->id, 'name' => 'CNAS + IRG', 'type' => 'deduction', 'amount' => 13900.0]);

        $anomalies = (new PayrollAnomalyService())->detectForRun($run->fresh(['paySlips']));

        $this->assertSame([], $anomalies);
    }

    public function test_duplicate_slip_is_detected(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = $this->makeRun($company);
        $this->makeSlip($run, $employee, 60000.0, 13900.0, 46100.0);

        // Le schéma réel interdit les doublons (UNIQUE payroll_run_id,
        // employee_id) — le détecteur couvre les données héritées d'avant la
        // contrainte. On désactive temporairement la contrainte pour simuler
        // cet état historique, puis on la restaure.
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE pay_slips DROP CONSTRAINT pay_slips_payroll_run_id_employee_id_unique');
        try {
            $this->makeSlip($run, $employee, 60000.0, 13900.0, 46100.0);
        } finally {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE pay_slips ADD CONSTRAINT pay_slips_payroll_run_id_employee_id_unique UNIQUE (payroll_run_id, employee_id)');
        }

        $anomalies = (new PayrollAnomalyService())->detectForRun($run->fresh(['paySlips']));

        $this->assertCount(1, $anomalies);
        $this->assertSame('duplicate_slip', $anomalies[0]['type']);
        $this->assertSame('high', $anomalies[0]['severity']);
    }

    public function test_incoherent_slip_is_detected(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = $this->makeRun($company);
        $slip = $this->makeSlip($run, $employee, 60000.0, 1000.0, 59000.0);
        // Brut déclaré 60 000 mais lignes = 70 000 → incohérence.
        PaySlipLine::create(['pay_slip_id' => $slip->id, 'name' => 'Salaire', 'type' => 'earning', 'amount' => 70000.0]);

        $anomalies = (new PayrollAnomalyService())->detectForRun($run->fresh(['paySlips']));

        $this->assertCount(1, $anomalies);
        $this->assertSame('incoherent_slip', $anomalies[0]['type']);
        $this->assertSame('high', $anomalies[0]['severity']);
    }

    public function test_gross_variance_above_threshold_is_detected(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $previous = $this->makeRun($company, 'validated', '2026-06');
        $this->makeSlip($previous, $employee, 60000.0, 13900.0, 46100.0);

        $current = $this->makeRun($company, 'calculated', '2026-07');
        $this->makeSlip($current, $employee, 120000.0, 30000.0, 90000.0); // +100 % → anomalie

        $anomalies = (new PayrollAnomalyService())->detectForRun($current->fresh(['paySlips']));

        $this->assertCount(1, $anomalies);
        $this->assertSame('gross_variance', $anomalies[0]['type']);
        $this->assertSame('high', $anomalies[0]['severity']);
    }

    public function test_small_variance_is_not_anomaly(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $previous = $this->makeRun($company, 'validated', '2026-06');
        $this->makeSlip($previous, $employee, 60000.0, 13900.0, 46100.0);

        $current = $this->makeRun($company, 'calculated', '2026-07');
        $this->makeSlip($current, $employee, 66000.0, 15000.0, 51000.0); // +10 % → OK

        $anomalies = (new PayrollAnomalyService())->detectForRun($current->fresh(['paySlips']));

        $this->assertSame([], $anomalies);
    }
}

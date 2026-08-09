<?php

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\CnasDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollJournalGenerator;
use Tests\TestCase;

/**
 * Programme FOCUS — F-10 : journal de paie + déclaration CNAS (structure + totaux).
 *
 * Données : 2 bulletins validés (60 000 et 40 000 DZD, CNAS 9 %/26 %).
 */
class PayrollExportsTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;


    private function seededRun(): array
    {
        $company = Company::factory()->create();
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Karim', 'last_name' => 'Benali']);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Yacine', 'last_name' => 'Cherif']);

        // Bulletin 60 000 : CNAS salariale 5 400, IRG 7 042, patronale 15 600, net 47 558.
        $slipA = $this->slip($run, $employeeA, 60000.0, 47558.0);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 60000.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => 5400.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => 7042.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Cotisations patronales', 'type' => 'employer_contribution', 'amount' => 15600.0]);

        // Bulletin 40 000 : CNAS salariale 3 600, IRG 3 500, patronale 10 400, net 32 900.
        $slipB = $this->slip($run, $employeeB, 40000.0, 32900.0);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 40000.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => 3600.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => 3500.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Cotisations patronales', 'type' => 'employer_contribution', 'amount' => 10400.0]);

        return [$run, $employeeA, $employeeB];
    }

    private function slip(PayrollRun $run, Employee $employee, float $gross, float $net): PaySlip
    {
        return PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'net_salary' => $net,
            'status' => 'validated',
        ]);
    }

    public function test_journal_de_paie_structure_and_totals(): void
    {
        [$run] = $this->seededRun();

        $csv = (new PayrollJournalGenerator())->generate($run);
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));

        // En-tête + 2 bulletins + ligne TOTAL.
        $this->assertCount(4, $lines);
        $this->assertSame('matricule', $lines[0][0]);

        $total = $lines[3];
        $this->assertSame('TOTAL', $total[0]);
        $this->assertSame('100000.00', $total[2]);   // brut
        $this->assertSame('9000.00', $total[3]);     // cotisations salariales
        $this->assertSame('10542.00', $total[4]);    // IRG
        $this->assertSame('0.00', $total[5]);        // autres déductions
        $this->assertSame('80458.00', $total[6]);    // net
        $this->assertSame('26000.00', $total[7]);    // coût employeur
    }

    public function test_cnas_declaration_structure_and_totals(): void
    {
        [$run] = $this->seededRun();

        $csv = (new CnasDeclarationGenerator())->generate($run);
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));

        $this->assertCount(4, $lines);
        $this->assertSame('assiette_brut', $lines[0][2]);

        // Ligne employé 1 : assiette 60 000, salariale 9 % = 5 400, patronale 26 % = 15 600.
        $this->assertSame('60000.00', $lines[1][2]);
        $this->assertSame('5400.00', $lines[1][3]);
        $this->assertSame('15600.00', $lines[1][4]);

        $total = $lines[3];
        $this->assertSame('100000.00', $total[2]);
        $this->assertSame('9000.00', $total[3]);
        $this->assertSame('26000.00', $total[4]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\PayrollJournalGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-4 (#1664) — Couverture Payroll ≥ 80 % : PayrollJournalGenerator
 * (F-10, #1540). Cas : run vide, bulletins validés avec lignes (cotisations
 * salariales, IRG, autres déductions, coût employeur), ligne de totaux,
 * injection de formule CSV neutralisée, matricule manquant.
 */
class PayrollJournalGeneratorCoverageTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_generates_csv_with_totals_row_for_validated_slips(): void
    {
        [$run, $employee] = $this->runWithSlips();

        $csv = (new PayrollJournalGenerator)->generate($run);

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(4, $lines); // header + 2 bulletins + TOTAL

        $this->assertSame(['matricule', 'nom', 'brut', 'cotisations_salariales', 'irg', 'autres_deductions', 'net_a_payer', 'cout_employeur'], $lines[0]);

        // Bulletin 1 : 60000 brut, 5000 cotisations, 3000 IRG, 2000 autres, net 50000, coût 9000
        $this->assertSame([
            (string) $employee->id, 'Jean Dupont',
            '60000.00', '5000.00', '3000.00', '2000.00', '50000.00', '9000.00',
        ], $lines[1]);

        $this->assertSame('TOTAL', $lines[3][0]);
        $this->assertSame('2 bulletins', $lines[3][1]);
        $this->assertSame('120000.00', $lines[3][2]);
        $this->assertSame('10000.00', $lines[3][3]);
        $this->assertSame('6000.00', $lines[3][4]);
        $this->assertSame('4000.00', $lines[3][5]);
        $this->assertSame('100000.00', $lines[3][6]);
        $this->assertSame('18000.00', $lines[3][7]);
    }

    public function test_generates_only_header_and_total_for_empty_run(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'locked',
        ]);

        $csv = (new PayrollJournalGenerator)->generate($run);

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(2, $lines);
        $this->assertSame('TOTAL', $lines[1][0]);
        $this->assertSame('0 bulletins', $lines[1][1]);
    }

    public function test_ignores_non_validated_slips(): void
    {
        [$run, $employee] = $this->runWithSlips('calculated');

        $csv = (new PayrollJournalGenerator)->generate($run);

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(2, $lines);
        $this->assertSame('0 bulletins', $lines[1][1]);
    }

    public function test_neutralizes_csv_formula_injection_cells(): void
    {
        [$run, $employee] = $this->runWithSlips('validated');

        // Matricule contrôlé par l'employé : injection =1+2 neutralisée (varchar(20)).
        $employee->update(['matricule' => '=1+2']);

        $run->refresh();

        $csv = (new PayrollJournalGenerator)->generate($run);

        $this->assertStringContainsString("'=1+2", $csv);
        $this->assertStringNotContainsString('",=1+2', $csv);
    }

    public function test_uses_employee_id_when_matricule_missing(): void
    {
        [$run, $employee] = $this->runWithSlips('validated');

        $run->refresh();

        $csv = (new PayrollJournalGenerator)->generate($run);

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertSame((string) $employee->id, $lines[1][0]);
    }

    /**
     * @return array{0: PayrollRun, 1: Employee}
     */
    private function runWithSlips(string $status = 'validated'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'matricule' => null,
        ]);

        /** @var Employee $employee2 */
        $employee2 = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'matricule' => null,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => 'locked',
        ]);

        // Contrainte unique (payroll_run_id, employee_id) : un bulletin par
        // employé par run — deux employés pour deux bulletins.
        foreach ([$employee, $employee2] as $target) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $target->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'status' => $status,
            ]);

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'amount' => 5000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Impot sur le revenu',
                'type' => 'deduction',
                'amount' => 3000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Avance',
                'type' => 'deduction',
                'amount' => 2000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Charges patronales',
                'type' => 'employer_contribution',
                'amount' => 9000,
            ]);
        }

        return [$run, $employee];
    }
}

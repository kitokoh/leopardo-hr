<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Modules\Payroll\Infrastructure\Services\DasDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollBordereauGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5243 — documents légaux DZ : DAS (déclaration annuelle des salaires),
 * bordereau de paie, formats virement CNEP/EDX — structure + totaux
 * (parsing round-trip) et gardes d'accès (RBAC/tenant/pays).
 *
 * Données : bulletins validés 60 000 (CNAS 5 400/15 600, IRG 7 042, net
 * 47 558) et 40 000 (CNAS 3 600/10 400, IRG 3 500, net 32 900).
 */
class DzLegalExportsTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Run DZ validé avec 2 bulletins (60 000 + 40 000) + lignes complètes.
     *
     * @return array{0: Company, 1: PayrollRun, 2: Employee, 3: Employee}
     */
    private function seededDzRun(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['metadata' => ['nis' => 'DZ-NIS-123456']]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'matricule' => 'MAT-A',
        ]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Yacine',
            'last_name' => 'Cherif',
            'matricule' => 'MAT-B',
        ]);

        $slipA = $this->slip($run, $employeeA, 60000.0, 47558.0, 15600.0, 126000.0);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 60000.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => 5400.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => 7042.0]);
        PaySlipLine::create(['pay_slip_id' => $slipA->id, 'name' => 'Cotisations patronales', 'type' => 'employer_contribution', 'amount' => 15600.0]);

        $slipB = $this->slip($run, $employeeB, 40000.0, 32900.0, 10400.0, 66000.0);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 40000.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => 3600.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => 3500.0]);
        PaySlipLine::create(['pay_slip_id' => $slipB->id, 'name' => 'Cotisations patronales', 'type' => 'employer_contribution', 'amount' => 10400.0]);

        return [$company, $run, $employeeA, $employeeB];
    }

    private function slip(PayrollRun $run, Employee $employee, float $gross, float $net, float $employer, float $cost): PaySlip
    {
        return PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'total_deductions' => round($gross - $net, 2),
            'net_salary' => $net,
            'employer_contributions' => $employer,
            'total_cost' => $cost,
            'status' => 'validated',
        ]);
    }

    // ── Bordereau ────────────────────────────────────────────────────────

    public function test_bordereau_structure_and_totals(): void
    {
        [$company, $run] = $this->seededDzRun();

        $content = (new PayrollBordereauGenerator)->generate($run);
        $rows = $this->parsePipeCsv($content, ';');

        $this->assertSame(['BORDEREAU', (string) $run->id, '2026-07-01', '2026-07-31', 'DZ'], $rows[0]);

        // Totaux par cotisation (ordre type + nom).
        $byLabel = $this->indexBy($rows);
        $this->assertSame('2', $byLabel['Cotisations salariales'][3]);
        $this->assertSame('9000.00', $byLabel['Cotisations salariales'][4]);
        $this->assertSame('2', $byLabel['Impot sur le revenu'][3]);
        $this->assertSame('10542.00', $byLabel['Impot sur le revenu'][4]);
        $this->assertSame('2', $byLabel['Cotisations patronales'][3]);
        $this->assertSame('26000.00', $byLabel['Cotisations patronales'][4]);

        // Récapitulatif run.
        $recap = $this->indexBy($rows);
        $this->assertSame('100000.00', $recap['brut_total'][2]);
        $this->assertSame('9000.00', $recap['cotisations_salariales'][2]);
        $this->assertSame('10542.00', $recap['irg'][2]);
        $this->assertSame('80458.00', $recap['net_total'][2]);
        $this->assertSame('26000.00', $recap['cotisations_patronales'][2]);
        $this->assertSame('192000.00', $recap['cout_employeur'][2]);
        $this->assertSame('2.00', $recap['bulletins'][2]);
    }

    public function test_bordereau_endpoint_manager_can_download(): void
    {
        [$company, $run] = $this->seededDzRun();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);
        $response = $this->get("/api/v1/payroll-runs/{$run->id}/bordereau");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('"BORDEREAU";', $response->streamedContent());
        $this->assertStringContainsString('"RECAP";"brut_total";"100000.00"', $response->streamedContent());
    }

    public function test_bordereau_endpoint_requires_manager_and_tenant_and_dz(): void
    {
        [$company, $run, $employeeA] = $this->seededDzRun();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        [$otherCompany] = $this->seededDzRun();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        // Employé non-manager → 403.
        Sanctum::actingAs($employee);
        $this->get("/api/v1/payroll-runs/{$run->id}/bordereau")->assertForbidden();

        // Cross-tenant → 404 (masquage d'existence).
        Sanctum::actingAs($otherManager);
        $this->get("/api/v1/payroll-runs/{$run->id}/bordereau")->assertNotFound();

        // Run non-DZ → 422.
        /** @var Company $maCompany */
        $maCompany = Company::factory()->create();
        $maRun = PayrollRun::create([
            'company_id' => $maCompany->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'MA',
            'status' => 'validated',
        ]);
        /** @var Employee $maManager */
        $maManager = Employee::factory()->manager()->create(['company_id' => $maCompany->id]);
        Sanctum::actingAs($maManager);
        $this->get("/api/v1/payroll-runs/{$maRun->id}/bordereau")->assertStatus(422);
    }

    // ── DAS ─────────────────────────────────────────────────────────────

    public function test_das_structure_and_totals(): void
    {
        [$company, $run, $employeeA, $employeeB] = $this->seededDzRun();
        $employeeA->update(['national_id' => 'NIS-A-0001']);
        $employeeB->update(['national_id' => 'NIS-B-0002']);

        // Run janvier pour A (test agrégation annuelle — un bulletin par
        // (run, employé), contrainte pay_slips_payroll_run_id_employee_id_unique).
        $janRun = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        $janSlip = $this->slip($janRun, $employeeA, 40000.0, 32900.0, 10400.0, 66000.0);
        PaySlipLine::create(['pay_slip_id' => $janSlip->id, 'name' => 'Salaire de base', 'type' => 'earning', 'amount' => 40000.0]);
        PaySlipLine::create(['pay_slip_id' => $janSlip->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => 3600.0]);
        PaySlipLine::create(['pay_slip_id' => $janSlip->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => 3500.0]);
        PaySlipLine::create(['pay_slip_id' => $janSlip->id, 'name' => 'Cotisations patronales', 'type' => 'employer_contribution', 'amount' => 10400.0]);

        $slips = PaySlip::query()
            ->where('company_id', $company->id)
            ->where('status', 'validated')
            ->with(['employee', 'lines'])
            ->get();

        $content = (new DasDeclarationGenerator)->generate('Leopardo DZ', 'DZ-NIS-123456', 2026, $slips);
        $rows = $this->parsePipeCsv($content);

        // ENTETE
        $this->assertSame('ENTETE', $rows[0][0]);
        $this->assertSame('Leopardo DZ', $rows[0][1]);
        $this->assertSame('DZ-NIS-123456', $rows[0][2]);
        $this->assertSame('2026', $rows[0][3]);

        // Une ligne par employé (A agrégé sur 2 mois, B sur 1 mois).
        $lines = array_values(array_filter($rows, static fn (array $row): bool => $row[0] === 'LIGNE'));
        $this->assertCount(2, $lines);

        $byNis = [];
        foreach ($lines as $line) {
            $byNis[$line[2]] = $line;
        }

        // A : 2 mois, brut 100 000, CNAS 9 000 / 26 000, IRG 10 542, net 80 458.
        $this->assertSame('2', $byNis['NIS-A-0001'][5]);
        $this->assertSame('100000.00', $byNis['NIS-A-0001'][6]);
        $this->assertSame('9000.00', $byNis['NIS-A-0001'][7]);
        $this->assertSame('26000.00', $byNis['NIS-A-0001'][8]);
        $this->assertSame('10542.00', $byNis['NIS-A-0001'][9]);
        $this->assertSame('80458.00', $byNis['NIS-A-0001'][10]);

        // B : 1 mois, brut 40 000, CNAS 3 600 / 10 400, IRG 3 500, net 32 900.
        $this->assertSame('1', $byNis['NIS-B-0002'][5]);
        $this->assertSame('40000.00', $byNis['NIS-B-0002'][6]);
        $this->assertSame('3600.00', $byNis['NIS-B-0002'][7]);
        $this->assertSame('10400.00', $byNis['NIS-B-0002'][8]);
        $this->assertSame('3500.00', $byNis['NIS-B-0002'][9]);
        $this->assertSame('32900.00', $byNis['NIS-B-0002'][10]);

        // TOTAUX : 2 employés, brut 140 000, CNAS 12 600 / 36 400, IRG 14 042, net 113 358.
        $totals = $rows[array_key_last($rows)];
        $this->assertSame('TOTAUX', $totals[0]);
        $this->assertSame('2', $totals[1]);
        $this->assertSame('140000.00', $totals[2]);
        $this->assertSame('12600.00', $totals[3]);
        $this->assertSame('36400.00', $totals[4]);
        $this->assertSame('14042.00', $totals[5]);
        $this->assertSame('113358.00', $totals[6]);
    }

    public function test_das_endpoint_aggregates_only_validated_dz_runs_of_year(): void
    {
        [$company, $run] = $this->seededDzRun();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        // Bulletin non validé → exclu de la DAS.
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 999999.0,
            'net_salary' => 999999.0,
            'status' => 'draft',
        ]);

        // Run MA (même tenant) → exclu de la DAS DZ.
        $maRun = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'MA',
            'status' => 'validated',
        ]);
        /** @var Employee $maEmployee */
        $maEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $this->slip($maRun, $maEmployee, 5000.0, 4500.0, 800.0, 5800.0);

        Sanctum::actingAs($manager);
        $response = $this->postJson('/api/v1/social-declarations/das-dz', ['year' => 2026]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'das_dz')
            ->assertJsonPath('data.employee_count', 2);

        $content = $response->json('data.content');
        $this->assertStringContainsString('TOTAUX|2|100000.00|9000.00|26000.00|10542.00|80458.00', $content);
        $this->assertStringNotContainsString('999999', $content);
        $this->assertStringNotContainsString('5000.00', $content);
    }

    public function test_das_endpoint_rbac_and_validation(): void
    {
        [$company] = $this->seededDzRun();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/social-declarations/das-dz', ['year' => 2026])->assertForbidden();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/social-declarations/das-dz', ['year' => 2019])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    // ── Formats virement CNEP / EDX ─────────────────────────────────────

    public function test_cnep_bank_export_round_trip(): void
    {
        [$company, $run] = $this->seededDzRun();

        $content = (new BankExportGenerator)->generate($run, 'cnep_dz');
        $rows = $this->parsePipeCsv($content);

        $this->assertSame('HEADER', $rows[0][0]);
        $this->assertSame('CNEP', $rows[0][1]);
        $this->assertSame('2', $rows[0][4]);
        $this->assertSame('80458.00', $rows[0][5]);
        $this->assertSame('DZD', $rows[0][6]);

        $details = array_values(array_filter($rows, static fn (array $row): bool => $row[0] === 'DETAIL'));
        $this->assertCount(2, $details);
        $this->assertSame('47558.00', $details[0][4]);
        $this->assertSame('32900.00', $details[1][4]);

        // Round-trip : total DÉTAILS = FOOTER.
        $footer = $rows[array_key_last($rows)];
        $this->assertSame('FOOTER', $footer[0]);
        $this->assertSame($footer[2], number_format(
            array_sum(array_map(static fn (array $row): float => (float) $row[4], $details)),
            2, '.', ''
        ));
    }

    public function test_edx_bank_export_round_trip(): void
    {
        [$company, $run] = $this->seededDzRun();

        $content = (new BankExportGenerator)->generate($run, 'edx_dz');
        $records = array_values(array_filter(explode("\r\n", trim($content))));

        $this->assertSame('H', $records[0][0]);
        $this->assertSame('D', $records[1][0]);
        $this->assertSame('D', $records[2][0]);
        $this->assertSame('F', $records[3][0]);

        // Entête : nombre + total.
        $this->assertSame('000002', substr($records[0], 29, 6));
        $this->assertSame('000000080458.00', substr($records[0], 35, 15));

        // Détails : séquence, RIB (20), nom (30), net (12,2).
        $netA = (float) substr($records[1], 57, 12);
        $netB = (float) substr($records[2], 57, 12);
        $this->assertSame(47558.0, $netA);
        $this->assertSame(32900.0, $netB);

        // Round-trip : total DÉTAILS = FIN.
        $this->assertSame('000000080458.00', substr($records[3], 7, 15));
    }

    public function test_bank_export_endpoint_accepts_cnep_and_edx_formats(): void
    {
        [$company, $run] = $this->seededDzRun();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", ['format' => 'cnep_dz'])->assertStatus(202);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", ['format' => 'edx_dz'])->assertStatus(202);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", ['format' => 'unknown'])->assertStatus(422);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Parse un CSV (pipe- ou point-virgule-délimité) en lignes de cellules.
     *
     * @param  non-empty-string  $separator
     * @return list<list<string>>
     */
    private function parsePipeCsv(string $content, string $separator = '|'): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): array => array_map(
                static fn (string $cell): string => trim($cell, '"'),
                explode($separator, $line)
            ),
            explode("\r\n", trim($content))
        ), static fn (array $row): bool => $row !== ['']));
    }

    /**
     * Indexe les lignes COTISATION (par nom, col 2) et RECAP (par label,
     * col 1) pour assertion ciblée.
     *
     * @param  list<list<string>>  $rows
     * @return array<string, list<string>>
     */
    private function indexBy(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            if (($row[0] ?? '') === 'COTISATION') {
                $index[$row[2]] = $row;
            } elseif (($row[0] ?? '') === 'RECAP') {
                $index[$row[1]] = $row;
            }
        }

        return $index;
    }
}

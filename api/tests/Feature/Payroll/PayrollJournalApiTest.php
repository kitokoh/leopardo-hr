<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-10 (#1540) : journal de paie exposé via l'API.
 *
 * GET /payroll-runs/{run}/journal → CSV (une ligne par bulletin validé +
 * ligne de totaux), réservé principal/comptable, isolé par tenant.
 */
class PayrollJournalApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $otherManager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);
        $this->otherManager = $otherManager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function seededRun(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        /** @var Employee $e1 */
        $e1 = Employee::factory()->create(['company_id' => $this->company->id]);
        /** @var Employee $e2 */
        $e2 = Employee::factory()->create(['company_id' => $this->company->id]);

        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $e1->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'net_salary' => 48000,
            'status' => 'validated',
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $e2->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 40000,
            'net_salary' => 32000,
            'status' => 'validated',
        ]);

        return $run;
    }

    public function test_journal_csv_download(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->seededRun();

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="journal_paie_2026-07-01_2026-07-31.csv"');

        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(4, $lines);
        $this->assertSame('matricule', $lines[0][0]);
        $this->assertSame('TOTAL', $lines[3][0]);
        $this->assertSame('100000.00', $lines[3][2]); // brut total
        $this->assertSame('80000.00', $lines[3][6]);  // net total
    }

    public function test_journal_only_returns_validated_slips(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->seededRun();
        /** @var Employee $e3 */
        $e3 = Employee::factory()->create(['company_id' => $this->company->id]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $e3->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 99999,
            'net_salary' => 99999,
            'status' => 'draft', // ne doit pas apparaître
        ]);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertOk();
        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(4, $lines); // header + 2 validés + total
        $this->assertSame('100000.00', $lines[3][2]);
    }

    public function test_employee_cannot_download_journal(): void
    {
        Sanctum::actingAs($this->employee);

        $run = $this->seededRun();

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertStatus(403);
    }

    public function test_cross_tenant_journal_is_forbidden(): void
    {
        Sanctum::actingAs($this->otherManager);

        $run = $this->seededRun();

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertStatus(404);
    }
}

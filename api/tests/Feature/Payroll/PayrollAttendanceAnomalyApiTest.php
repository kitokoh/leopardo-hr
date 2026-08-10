<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PayrollAnomalyService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-20 (#1550) : écarts pointage → paie signalés avant
 * clôture (heures sup pointées non intégrées au bulletin), exposés via
 * GET /payroll-runs/{run}/anomalies.
 */
class PayrollAttendanceAnomalyApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

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
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function makeRun(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'calculated',
        ]);

        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $this->employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'net_salary' => 48000,
            'status' => 'calculated',
            'overtime_hours' => 0,
        ]);

        return $run;
    }

    public function test_missing_overtime_is_flagged_before_closing(): void
    {
        // 5 h sup pointées, 0 h intégrées → anomalie attendance_vs_payroll.
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 5,
            'status' => 'ontime',
        ]);

        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);
        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $response['total']);
        $this->assertSame('attendance_vs_payroll', $response['anomalies'][0]['type']);
        $this->assertSame($this->employee->id, $response['anomalies'][0]['employee_id']);
        $this->assertStringContainsString('5.00 h sup pointées', $response['anomalies'][0]['message']);
    }

    public function test_small_difference_within_tolerance_is_not_flagged(): void
    {
        // 1 h pointée, 0 h intégrée → sous le seuil de tolérance (2 h).
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 1,
            'status' => 'ontime',
        ]);

        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_flagged_overtime_is_not_flagged_when_paid(): void
    {
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 5,
            'status' => 'ontime',
        ]);

        $run = $this->makeRun();
        $run->paySlips()->update(['overtime_hours' => 5]);

        Sanctum::actingAs($this->manager);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_cancelled_attendance_logs_are_ignored(): void
    {
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 8,
            'status' => 'incomplete',
        ]);

        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_employee_cannot_read_anomalies(): void
    {
        $run = $this->makeRun();

        Sanctum::actingAs($this->employee);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")->assertStatus(403);
    }

    public function test_cross_tenant_anomalies_are_forbidden(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);

        $run = $this->makeRun();

        Sanctum::actingAs($otherManager);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/anomalies")->assertStatus(404);
    }

    public function test_service_detects_high_severity_for_large_gap(): void
    {
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 10,
            'status' => 'ontime',
        ]);

        $run = $this->makeRun();

        $anomalies = (new PayrollAnomalyService())->detectForRun($run);
        $attendance = collect($anomalies)->firstWhere('type', 'attendance_vs_payroll');

        $this->assertNotNull($attendance);
        $this->assertSame('high', $attendance['severity']);
    }
}

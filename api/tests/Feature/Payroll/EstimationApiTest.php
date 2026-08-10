<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-4 (#1664) — couverture du contrôleur d'estimation paie.
 *
 * `EstimationController` (module Payroll) expose daily-summary /
 * quick-estimate / receipt pour les managers. Ces routes étaient sans test
 * Feature direct : 0 statement couvert dans le gate de coverage Payroll.
 * Ce fichier complète la couverture (F-14 / #1602) et verrouille le contrat
 * RBAC + validation.
 */
class EstimationApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'Africa/Algiers']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;

        // Quelques logs de pointage sur la période d'estimation.
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'check_in' => '2026-07-10 08:00:00',
            'check_out' => '2026-07-10 17:00:00',
            'hours_worked' => 8.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-11',
            'check_in' => '2026-07-11 08:00:00',
            'check_out' => '2026-07-11 16:00:00',
            'hours_worked' => 7.0,
            'overtime_hours' => 0.0,
            'status' => 'ontime',
        ]);
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-12',
            'check_in' => '2026-07-12 08:30:00',
            'check_out' => '2026-07-12 12:00:00',
            'hours_worked' => 3.5,
            'overtime_hours' => 0.0,
            'status' => 'late',
        ]);
    }

    private function actingAsManager(): void
    {
        Sanctum::actingAs($this->manager);
    }

    public function test_quick_estimate_returns_daily_breakdown_for_manager(): void
    {
        $this->actingAsManager();

        $response = $this->getJson("/api/v1/employees/{$this->employee->id}/quick-estimate?from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertJsonStructure(['data' => ['period', 'totals' => ['gross'], 'breakdown']]);

        $this->assertSame(3, $response->json('data.period.days_present'));
        $this->assertGreaterThan(0, $response->json('data.totals.gross'));
        $this->assertCount(3, $response->json('data.breakdown'));
    }

    public function test_daily_summary_returns_sessions_for_given_date(): void
    {
        $this->actingAsManager();

        $this->getJson("/api/v1/employees/{$this->employee->id}/daily-summary?date=2026-07-10")
            ->assertOk()
            ->assertJsonPath('data.date', '2026-07-10')
            ->assertJsonPath('data.sessions_count', 1);
    }

    public function test_receipt_returns_pdf_document(): void
    {
        $this->actingAsManager();

        $response = $this->get("/api/v1/employees/{$this->employee->id}/receipt?from=2026-07-01&to=2026-07-31");
        $response->assertOk();
        $this->assertStringContainsString('pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_estimation_requires_valid_period(): void
    {
        $this->actingAsManager();

        $this->getJson("/api/v1/employees/{$this->employee->id}/quick-estimate")
            ->assertStatus(422);

        $this->getJson("/api/v1/employees/{$this->employee->id}/quick-estimate?from=2026-07-31&to=2026-07-01")
            ->assertStatus(422);
    }

    public function test_estimation_requires_manager_role(): void
    {
        /** @var Employee $employeeRole */
        $employeeRole = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employeeRole);

        $this->getJson("/api/v1/employees/{$this->employee->id}/quick-estimate?from=2026-07-01&to=2026-07-31")
            ->assertForbidden();
    }

    public function test_estimation_rejects_unknown_employee(): void
    {
        $this->actingAsManager();

        $this->getJson('/api/v1/employees/999999999/quick-estimate?from=2026-07-01&to=2026-07-31')
            ->assertNotFound();
    }
}

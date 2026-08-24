<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5269 — tests par parcours : rapports de pointage PAR PÉRIODE
 * (moteur #5268, mergé 2026-08-23) — day/week + filtres employé + export CSV,
 * isolation RBAC. Complète AttendanceMonthlyReportTest (month par défaut).
 */
class AttendancePeriodReportsTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{company: Company, manager: Employee, employee: Employee}
     */
    private function seedCompanyWithLogs(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'UTC']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Kaci',
            'matricule' => 'EMP-001',
            'salary_base' => 173330,
        ]);

        app()->instance('current_company', $company);
        // 3 jours dans la semaine ISO 2026-W19 (lun 04/05 → dim 10/05).
        foreach (['2026-05-04', '2026-05-05', '2026-05-07'] as $day) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $day,
                'check_in' => Carbon::parse("{$day} 08:30:00", 'UTC'),
                'check_out' => Carbon::parse("{$day} 17:30:00", 'UTC'),
                'hours_worked' => 8,
                'overtime_hours' => 1,
                'late_minutes' => 10,
            ]);
        }
        app()->forgetInstance('current_company');

        return compact('company', 'manager', 'employee');
    }

    public function test_manager_gets_weekly_report_with_employee_filter(): void
    {
        ['manager' => $manager, 'employee' => $employee] = $this->seedCompanyWithLogs();

        Sanctum::actingAs($manager);

        $response = $this->getJson(
            "/api/v1/attendance/monthly-report?period=week&week=2026-05-04&employee_id={$employee->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'week');
        $response->assertJsonPath('data.period.date_from', '2026-05-04');
        $response->assertJsonPath('data.period.date_to', '2026-05-10');
        $response->assertJsonPath('data.totals.worked_days', 3);
        $response->assertJsonPath('data.totals.worked_hours', 24);
        $response->assertJsonPath('data.totals.overtime_hours', 3);
    }

    public function test_manager_gets_daily_report(): void
    {
        ['manager' => $manager] = $this->seedCompanyWithLogs();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=day&date=2026-05-05');

        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'day');
        $response->assertJsonPath('data.period.date_from', '2026-05-05');
        $response->assertJsonPath('data.totals.worked_days', 1);
        $response->assertJsonPath('data.totals.late_minutes', 10);
    }

    public function test_weekly_report_csv_export(): void
    {
        ['manager' => $manager, 'employee' => $employee] = $this->seedCompanyWithLogs();

        Sanctum::actingAs($manager);

        $response = $this->get(
            "/api/v1/attendance/monthly-report?period=week&week=2026-05-04&employee_id={$employee->id}&format=csv"
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee('EMP-001');
    }

    public function test_employee_cannot_access_period_reports(): void
    {
        ['employee' => $employee] = $this->seedCompanyWithLogs();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/attendance/monthly-report?period=week&week=2026-05-04')
            ->assertForbidden();
    }
}

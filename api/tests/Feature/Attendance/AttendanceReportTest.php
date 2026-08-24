<?php

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Rapports de pointage par période (issue #5268) :
 * journalier, hebdomadaire, mensuel ; filtres équipe/employé ;
 * exports CSV/PDF ; scope manager conservé.
 */
class AttendanceReportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'UTC']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function log(Company $company, Employee $employee, string $date, float $hours, float $overtime = 0.0, int $late = 0): void
    {
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'check_in' => Carbon::parse($date.' 08:30:00', 'UTC'),
            'check_out' => Carbon::parse($date.' 18:30:00', 'UTC'),
            'hours_worked' => $hours,
            'overtime_hours' => $overtime,
            'late_minutes' => $late,
        ]);
    }

    /**
     * Le rapport liste tous les employés de l'entreprise (manager inclus) ;
     * on cherche donc la ligne par employee_id plutôt que par indice.
     *
     * @param  TestResponse<JsonResponse>  $response
     * @return array<string, mixed>|null
     */
    private function employeeRow(TestResponse $response, int $employeeId): ?array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) $response->json('data.employees');

        /** @var array<string, mixed>|null $row */
        $row = collect($rows)->firstWhere('employee_id', $employeeId);

        return is_array($row) ? $row : null;
    }

    public function test_manager_can_get_daily_report_scoped_to_the_day(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Kaci',
            'salary_base' => 173330,
            'hourly_rate' => null,
        ]);

        $this->bindCompany($company);
        // Deux logs : un dans la journée ciblée, un hors période.
        $this->log($company, $employee, '2026-05-06', 9, 1, 15);
        $this->log($company, $employee, '2026-05-12', 8);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=day&date=2026-05-06');
        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'day');
        $response->assertJsonPath('data.period.date_from', '2026-05-06');
        $response->assertJsonPath('data.period.date_to', '2026-05-06');
        $response->assertJsonPath('data.totals.worked_hours', 9);
        $response->assertJsonPath('data.totals.worked_days', 1);

        $row = $this->employeeRow($response, (int) $employee->id);
        $this->assertNotNull($row);
        // Les montants JSON arrondis (9000.0) sont décodés en int (9000).
        $this->assertSame(9, $row['worked_hours']);
        $this->assertSame(1, $row['overtime_hours']);
        $this->assertSame(15, $row['late_minutes']);
        $this->assertSame(9000, $row['estimated_gross_amount']);
    }

    public function test_manager_can_get_weekly_report_covering_the_iso_week(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Sofia',
            'last_name' => 'Bouzid',
            'salary_base' => 173330,
        ]);

        $this->bindCompany($company);
        // 2026-05-06 = mercredi de la semaine ISO 2026-05-04 → 2026-05-10.
        $this->log($company, $employee, '2026-05-04', 8); // lundi
        $this->log($company, $employee, '2026-05-06', 9, 1, 15); // mercredi
        $this->log($company, $employee, '2026-05-11', 8); // lundi suivant — hors semaine
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=week&week=2026-05-06');
        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'week');
        $response->assertJsonPath('data.period.date_from', '2026-05-04');
        $response->assertJsonPath('data.period.date_to', '2026-05-10');
        $response->assertJsonPath('data.totals.worked_hours', 17);
        $response->assertJsonPath('data.totals.worked_days', 2);
        $response->assertJsonPath('data.totals.late_minutes', 15);
        $response->assertJsonPath('data.totals.estimated_overtime_pay', 1500);

        $row = $this->employeeRow($response, (int) $employee->id);
        $this->assertNotNull($row);
        $this->assertSame(17, $row['worked_hours']);
        $this->assertSame(2, $row['worked_days']);
    }

    public function test_month_period_param_matches_legacy_month_report(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 173330,
        ]);

        $this->bindCompany($company);
        $this->log($company, $employee, '2026-05-06', 9);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=month&month=2026-05');
        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'month');
        $response->assertJsonPath('data.period.month', '2026-05');
        $response->assertJsonPath('data.period.date_from', '2026-05-01');
        $response->assertJsonPath('data.period.date_to', '2026-05-31');
        $response->assertJsonPath('data.totals.worked_hours', 9);
    }

    public function test_default_period_is_month_without_parameters(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report');
        $response->assertOk();
        $response->assertJsonPath('data.period.type', 'month');
        $this->assertSame(now('UTC')->format('Y-m'), $response->json('data.period.month'));
    }

    public function test_department_filter_limits_report_to_that_team(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $deptA = Department::create(['name' => 'Operations']);
        $deptA->company_id = $company->id;
        $deptA->save();

        $deptB = Department::create(['name' => 'Comptabilite']);
        $deptB->company_id = $company->id;
        $deptB->save();

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Team', 'last_name' => 'Alpha', 'department_id' => $deptA->id]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Team', 'last_name' => 'Bravo', 'department_id' => $deptB->id]);

        $this->bindCompany($company);
        $this->log($company, $employeeA, '2026-05-06', 8);
        $this->log($company, $employeeB, '2026-05-06', 8);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=month&month=2026-05&department_id='.$deptA->id);
        $response->assertOk();
        $this->assertCount(1, $response->json('data.employees'));

        $row = $this->employeeRow($response, (int) $employeeA->id);
        $this->assertNotNull($row);
        $this->assertSame($deptA->id, $row['department_id']);
        $this->assertSame('Operations', $row['department_name']);
        $this->assertNull($this->employeeRow($response, (int) $employeeB->id));
    }

    public function test_employee_filter_returns_single_employee_sheet(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'One', 'last_name' => 'A']);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Two', 'last_name' => 'B']);

        $this->bindCompany($company);
        $this->log($company, $employeeA, '2026-05-06', 8);
        $this->log($company, $employeeB, '2026-05-06', 8);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/monthly-report?period=month&month=2026-05&employee_id='.$employeeB->id);
        $response->assertOk();
        $this->assertCount(1, $response->json('data.employees'));
        $this->assertSame($employeeB->id, $response->json('data.employees.0.employee_id'));
        $this->assertNull($this->employeeRow($response, (int) $employeeA->id));
    }

    public function test_weekly_report_csv_export(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-042',
            'salary_base' => 173330,
        ]);

        $this->bindCompany($company);
        $this->log($company, $employee, '2026-05-06', 9);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $csv = $this->get('/api/v1/attendance/monthly-report?period=week&week=2026-05-06&format=csv');
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv->assertHeader('Content-Disposition', 'attachment; filename="attendance-report-week-2026-05-04_2026-05-10.csv"');
        $csv->assertSee('EMP-042');
        $csv->assertSee('department_name');
    }

    public function test_daily_report_pdf_export(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'salary_base' => 173330]);

        $this->bindCompany($company);
        $this->log($company, $employee, '2026-05-06', 8);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $pdf = $this->get('/api/v1/attendance/monthly-report?period=day&date=2026-05-06&format=pdf');
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $pdf->assertHeader('Content-Disposition', 'attachment; filename=attendance-report-day-2026-05-06_2026-05-06.pdf');
    }

    public function test_scoped_dept_manager_cannot_widen_scope_with_department_filter(): void
    {
        $company = $this->company();

        $deptOwned = Department::create(['name' => 'Ma Team']);
        $deptOwned->company_id = $company->id;
        $deptOwned->save();

        $deptOther = Department::create(['name' => 'Autre Team']);
        $deptOther->company_id = $company->id;
        $deptOther->save();

        /** @var Employee $manager */
        $manager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => $deptOwned->id,
        ]);
        /** @var Employee $ownedEmployee */
        $ownedEmployee = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Dans', 'last_name' => 'Scope', 'department_id' => $deptOwned->id]);
        Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Hors', 'last_name' => 'Scope', 'department_id' => $deptOther->id]);

        $this->bindCompany($company);
        $this->log($company, $ownedEmployee, '2026-05-06', 8);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        // Le manager demande le département de l'AUTRE équipe : filtres et
        // scope PA2-SEC-002 sont combinés (AND) → zéro ligne, aucune fuite.
        $response = $this->getJson('/api/v1/attendance/monthly-report?period=month&month=2026-05&department_id='.$deptOther->id);
        $response->assertOk();
        $this->assertCount(0, $response->json('data.employees'));
        $this->assertSame(0, $response->json('data.totals.employees'));

        // Sans filtre, le manager voit bien uniquement SA team (manager + équipe).
        $scoped = $this->getJson('/api/v1/attendance/monthly-report?period=month&month=2026-05');
        $scoped->assertOk();
        /** @var array<int, array<string, mixed>> $scopedRows */
        $scopedRows = (array) $scoped->json('data.employees');
        $names = collect($scopedRows)->pluck('name')->all();
        $this->assertContains('Dans Scope', $names);
        $this->assertNotContains('Hors Scope', $names);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/attendance/monthly-report?period=year')->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_summary_counts_only_the_authenticated_company(): void
    {
        [$company, $manager] = $this->tenantFixture();
        $otherCompany = Company::factory()->create();

        Employee::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'active']);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'suspended']);
        Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);

        DB::table('departments')->insert([
            ['company_id' => $company->id, 'name' => 'RH', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $otherCompany->id, 'name' => 'Other', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('attendance_logs')->insert([
            [
                'company_id' => $company->id,
                'employee_id' => $manager->id,
                'date' => now()->toDateString(),
                'check_in' => now(),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $otherCompany->id,
                'employee_id' => Employee::factory()->create(['company_id' => $otherCompany->id])->id,
                'date' => now()->toDateString(),
                'check_in' => now(),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->insertAbsence($company->id, $manager->id, 'pending');
        $this->insertAbsence($otherCompany->id, Employee::factory()->create(['company_id' => $otherCompany->id])->id, 'pending');

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.employees_total', 4)
            ->assertJsonPath('data.employees_active', 3)
            ->assertJsonPath('data.departments', 1)
            ->assertJsonPath('data.today_attendance', 1)
            ->assertJsonPath('data.pending_absences', 1);
    }

    public function test_recent_activity_is_company_scoped_and_limited(): void
    {
        [$company, $manager] = $this->tenantFixture();
        $otherCompany = Company::factory()->create();

        DB::table('audit_logs')->insert([
            [
                'company_id' => $company->id,
                'user_id' => $manager->id,
                'action' => 'employee.created',
                'auditable_type' => Employee::class,
                'auditable_id' => $manager->id,
                'created_at' => now()->subMinute(),
            ],
            [
                'company_id' => $company->id,
                'user_id' => $manager->id,
                'action' => 'absence.approved',
                'auditable_type' => 'absence',
                'auditable_id' => 1,
                'created_at' => now(),
            ],
            [
                'company_id' => $otherCompany->id,
                'user_id' => 999,
                'action' => 'foreign.activity',
                'auditable_type' => 'company',
                'auditable_id' => 1,
                'created_at' => now(),
            ],
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/dashboard/recent-activity?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'absence.approved');
    }

    public function test_kpi_uses_month_window_without_postgres_specific_sql(): void
    {
        [$company, $manager] = $this->tenantFixture();
        $month = now()->format('Y-m');

        $manager->forceFill(['created_at' => now()->subMonths(2)])->save();
        Employee::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'active']);
        $archived = Employee::factory()->create(['company_id' => $company->id, 'status' => 'archived']);
        DB::table('employees')->where('id', $archived->id)->update(['archived_at' => now()]);
        $this->insertAbsence($company->id, $manager->id, 'approved');

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/dashboard/kpi?month={$month}")
            ->assertOk()
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.turnover', 1)
            ->assertJsonPath('data.new_hires', 3)
            ->assertJsonPath('data.total_active_employees', 3)
            ->assertJsonPath('data.absence_rate', 33.3);
    }

    public function test_manager_digest_uses_real_today_data_and_is_company_scoped(): void
    {
        [$company, $manager] = $this->tenantFixture();
        $otherCompany = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);

        DB::table('attendance_logs')->insert([
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => now()->toDateString(),
                'check_in' => now()->subHours(2),
                'check_out' => null,
                'status' => 'late',
                'late_minutes' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $otherCompany->id,
                'employee_id' => $foreignEmployee->id,
                'date' => now()->toDateString(),
                'check_in' => now()->subHours(2),
                'check_out' => null,
                'status' => 'late',
                'late_minutes' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->insertAbsence($company->id, $employee->id, 'pending');
        $this->insertSalaryAdvance($company->id, $employee->id, 'pending');
        $this->insertSalaryAdvance($otherCompany->id, $foreignEmployee->id, 'pending');

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/dashboard/manager-digest')
            ->assertOk()
            ->assertJsonPath('data.team_scope', 'company')
            ->assertJsonPath('data.present', 1)
            ->assertJsonPath('data.late', 1)
            ->assertJsonPath('data.open_sessions', 1)
            ->assertJsonPath('data.pending_absences', 1)
            ->assertJsonPath('data.pending_salary_advances', 1)
            ->assertJsonPath('data.pending_actions', 2)
            ->assertJsonPath('data.items.0.route', '/manager/attendance');
    }

    public function test_department_manager_digest_is_limited_to_their_direct_team(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerDept()->create(['company_id' => $company->id]);
        $managed = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
            'status' => 'active',
        ]);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        DB::table('attendance_logs')->insert([
            [
                'company_id' => $company->id,
                'employee_id' => $managed->id,
                'date' => now()->toDateString(),
                'check_in' => now()->subHours(2),
                'check_out' => null,
                'status' => 'present',
                'late_minutes' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'employee_id' => $otherEmployee->id,
                'date' => now()->toDateString(),
                'check_in' => now()->subHours(2),
                'check_out' => null,
                'status' => 'late',
                'late_minutes' => 33,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/dashboard/manager-digest')
            ->assertOk()
            ->assertJsonPath('data.team_scope', 'managed_team')
            ->assertJsonPath('data.team_size', 2)
            ->assertJsonPath('data.present', 1)
            ->assertJsonPath('data.late', 0)
            ->assertJsonPath('data.open_sessions', 1);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function tenantFixture(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    private function insertAbsence(string $companyId, int $employeeId, string $status): void
    {
        $typeId = DB::table('absence_types')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Conges',
            'code' => 'leave_'.uniqid(),
            'created_at' => now(),
        ]);

        DB::table('absences')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'absence_type_id' => $typeId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days_count' => 1,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSalaryAdvance(string $companyId, int $employeeId, string $status): void
    {
        DB::table('salary_advances')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'amount' => 5000,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

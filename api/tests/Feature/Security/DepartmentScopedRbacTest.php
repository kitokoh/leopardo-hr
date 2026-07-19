<?php

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-SEC-002: manager_role=dept doit etre reellement scope au departement
 * de l'acteur (via Employee.department_id), pour EmployeePolicy,
 * AttendancePolicy, SchedulePolicy (via ScheduleController), EvaluationPolicy
 * et DepartmentPolicy.
 */
class DepartmentScopedRbacTest extends TestCase
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

    /**
     * @return array{
     *     company: Company,
     *     deptA: Department,
     *     deptB: Department,
     *     managerA: Employee,
     *     managerB: Employee,
     *     managerNoDept: Employee,
     *     employeeA: Employee,
     *     employeeB: Employee,
     * }
     */
    private function seedScopedCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Scoped',
            'slug' => 'company-scoped',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'scoped@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $deptA = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Department A',
        ]);

        $deptB = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Department B',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $company->id,
            'department_id' => $deptA->id,
            'email' => 'manager-a@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $managerB = Employee::query()->create([
            'company_id' => $company->id,
            'department_id' => $deptB->id,
            'email' => 'manager-b@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $managerNoDept = Employee::query()->create([
            'company_id' => $company->id,
            'department_id' => null,
            'email' => 'manager-nodept@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'department_id' => $deptA->id,
            'email' => 'employee-a@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'department_id' => $deptB->id,
            'email' => 'employee-b@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [
            'company' => $company,
            'deptA' => $deptA,
            'deptB' => $deptB,
            'managerA' => $managerA,
            'managerB' => $managerB,
            'managerNoDept' => $managerNoDept,
            'employeeA' => $employeeA,
            'employeeB' => $employeeB,
        ];
    }

    public function test_dept_manager_lists_only_employees_of_own_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('manager-a@scoped.test', $emails);
        $this->assertContains('employee-a@scoped.test', $emails);
        $this->assertNotContains('employee-b@scoped.test', $emails);
        $this->assertNotContains('manager-b@scoped.test', $emails);
    }

    public function test_dept_manager_without_department_sees_no_employees(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerNoDept']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertOk();

        $this->assertSame([], $response->json('data'));
    }

    public function test_dept_manager_cannot_view_employee_from_other_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $ownDept = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees/'.$seed['employeeA']->id);
        $ownDept->assertOk();

        $otherDept = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees/'.$seed['employeeB']->id);
        $otherDept->assertForbidden();
    }

    public function test_dept_manager_attendance_today_scoped_to_own_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/today');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'collection');

        $ids = collect($response->json('data.items'))
            ->pluck('employee_id')
            ->all();

        $this->assertContains($seed['employeeA']->id, $ids);
        $this->assertNotContains($seed['employeeB']->id, $ids);
    }

    public function test_dept_manager_cannot_view_attendance_for_employee_outside_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance?employee_id='.$seed['employeeB']->id);

        $response->assertForbidden();
    }

    public function test_dept_manager_attendance_index_all_scoped_to_own_department(): void
    {
        $seed = $this->seedScopedCompany();

        AttendanceLog::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeA']->id,
            'date' => now()->toDateString(),
            'session_number' => 1,
            'status' => 'complete',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'date' => now()->toDateString(),
            'session_number' => 1,
            'status' => 'complete',
        ]);

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance');

        $response->assertOk();

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->all();
        $this->assertContains($seed['employeeA']->id, $employeeIds);
        $this->assertNotContains($seed['employeeB']->id, $employeeIds);
    }

    public function test_dept_manager_can_only_assign_schedule_to_own_department_employees(): void
    {
        $seed = $this->seedScopedCompany();

        $schedule = Schedule::query()->create([
            'company_id' => $seed['company']->id,
            'name' => 'Standard',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $ownDeptResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/'.$schedule->id.'/assign-employees', [
                'employee_ids' => [$seed['employeeA']->id],
            ]);
        $ownDeptResponse->assertOk();

        $seed['employeeA']->refresh();
        $this->assertSame($schedule->id, $seed['employeeA']->schedule_id);

        $otherDeptResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/'.$schedule->id.'/assign-employees', [
                'employee_ids' => [$seed['employeeB']->id],
            ]);
        $otherDeptResponse->assertStatus(422);

        $seed['employeeB']->refresh();
        $this->assertNotSame($schedule->id, $seed['employeeB']->schedule_id);
    }

    public function test_dept_manager_cannot_view_department_of_another_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $ownDept = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/departments/'.$seed['deptA']->id);
        $ownDept->assertOk();

        $otherDept = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/departments/'.$seed['deptB']->id);
        $otherDept->assertForbidden();
    }

    public function test_dept_manager_department_index_scoped_to_own_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/departments');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($seed['deptA']->id, $ids);
        $this->assertNotContains($seed['deptB']->id, $ids);
    }

    public function test_dept_manager_cannot_create_evaluation_for_employee_outside_department(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/evaluations', [
                'employee_id' => $seed['employeeB']->id,
                'period' => '2026-01',
            ]);

        $response->assertForbidden();
    }

    public function test_dept_manager_can_create_evaluation_for_own_department_employee(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/evaluations', [
                'employee_id' => $seed['employeeA']->id,
                'period' => '2026-01',
            ]);

        $response->assertCreated();
    }

    public function test_dept_manager_evaluation_index_scoped_to_own_department(): void
    {
        $seed = $this->seedScopedCompany();

        Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeA']->id,
            'evaluator_id' => $seed['managerA']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'evaluator_id' => $seed['managerB']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/evaluations');

        $response->assertOk();

        $employeeIds = collect($response->json('data'))->pluck('employee.id')->all();
        $this->assertContains($seed['employeeA']->id, $employeeIds);
        $this->assertNotContains($seed['employeeB']->id, $employeeIds);
    }

    public function test_dept_manager_cannot_view_or_update_evaluation_outside_department(): void
    {
        $seed = $this->seedScopedCompany();

        $evaluation = Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'evaluator_id' => $seed['managerB']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        $token = $seed['managerA']->createToken('tests')->plainTextToken;

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/evaluations/'.$evaluation->id);
        $show->assertForbidden();

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/evaluations/'.$evaluation->id, [
                'score' => 4.5,
            ]);
        $update->assertForbidden();
    }
}

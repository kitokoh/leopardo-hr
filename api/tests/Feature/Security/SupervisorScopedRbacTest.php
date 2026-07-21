<?php

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-SEC-003: manager_role=superviseur doit etre reellement scope a son
 * equipe assignee (via Employee.manager_id), et non a l'ensemble de
 * l'entreprise, pour EmployeePolicy, AttendancePolicy, SchedulePolicy (via
 * ScheduleController) et EvaluationPolicy. Miroir de
 * DepartmentScopedRbacTest (PA2-SEC-002) pour le role superviseur.
 */
class SupervisorScopedRbacTest extends TestCase
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
     *     supervisorA: Employee,
     *     supervisorB: Employee,
     *     supervisorNoTeam: Employee,
     *     employeeA: Employee,
     *     employeeB: Employee,
     * }
     */
    private function seedScopedCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Supervised',
            'slug' => 'company-supervised',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'supervised@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $supervisorA = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'supervisor-a@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'superviseur',
            'status' => 'active',
        ]);

        $supervisorB = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'supervisor-b@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'superviseur',
            'status' => 'active',
        ]);

        $supervisorNoTeam = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'supervisor-noteam@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'superviseur',
            'status' => 'active',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'manager_id' => $supervisorA->id,
            'email' => 'employee-a@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'manager_id' => $supervisorB->id,
            'email' => 'employee-b@scoped.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [
            'company' => $company,
            'supervisorA' => $supervisorA,
            'supervisorB' => $supervisorB,
            'supervisorNoTeam' => $supervisorNoTeam,
            'employeeA' => $employeeA,
            'employeeB' => $employeeB,
        ];
    }

    public function test_supervisor_lists_only_directly_assigned_employees(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('supervisor-a@scoped.test', $emails);
        $this->assertContains('employee-a@scoped.test', $emails);
        $this->assertNotContains('employee-b@scoped.test', $emails);
        $this->assertNotContains('supervisor-b@scoped.test', $emails);
    }

    public function test_supervisor_without_assigned_team_sees_only_self(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorNoTeam']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertSame(['supervisor-noteam@scoped.test'], $emails);
    }

    public function test_supervisor_cannot_view_employee_outside_own_team(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $ownTeam = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees/'.$seed['employeeA']->id);
        $ownTeam->assertOk();

        $otherTeam = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employees/'.$seed['employeeB']->id);
        $otherTeam->assertForbidden();
    }

    public function test_supervisor_attendance_today_scoped_to_own_team(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

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

    public function test_supervisor_cannot_view_attendance_for_employee_outside_team(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance?employee_id='.$seed['employeeB']->id);

        $response->assertForbidden();
    }

    public function test_supervisor_attendance_index_all_scoped_to_own_team(): void
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

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance');

        $response->assertOk();

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->all();
        $this->assertContains($seed['employeeA']->id, $employeeIds);
        $this->assertNotContains($seed['employeeB']->id, $employeeIds);
    }

    public function test_supervisor_can_only_assign_schedule_to_own_team_employees(): void
    {
        $seed = $this->seedScopedCompany();

        $schedule = Schedule::query()->create([
            'company_id' => $seed['company']->id,
            'name' => 'Standard',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $ownTeamResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/'.$schedule->id.'/assign-employees', [
                'employee_ids' => [$seed['employeeA']->id],
            ]);
        $ownTeamResponse->assertOk();

        $seed['employeeA']->refresh();
        $this->assertSame($schedule->id, $seed['employeeA']->schedule_id);

        $otherTeamResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/'.$schedule->id.'/assign-employees', [
                'employee_ids' => [$seed['employeeB']->id],
            ]);
        $otherTeamResponse->assertStatus(422);

        $seed['employeeB']->refresh();
        $this->assertNotSame($schedule->id, $seed['employeeB']->schedule_id);
    }

    public function test_supervisor_cannot_create_evaluation_for_employee_outside_team(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/evaluations', [
                'employee_id' => $seed['employeeB']->id,
                'period' => '2026-01',
            ]);

        $response->assertForbidden();
    }

    public function test_supervisor_can_create_evaluation_for_own_team_employee(): void
    {
        $seed = $this->seedScopedCompany();

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/evaluations', [
                'employee_id' => $seed['employeeA']->id,
                'period' => '2026-01',
            ]);

        $response->assertCreated();
    }

    public function test_supervisor_evaluation_index_scoped_to_own_team(): void
    {
        $seed = $this->seedScopedCompany();

        Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeA']->id,
            'evaluator_id' => $seed['supervisorA']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'evaluator_id' => $seed['supervisorB']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/evaluations');

        $response->assertOk();

        $employeeIds = collect($response->json('data'))->pluck('employee.id')->all();
        $this->assertContains($seed['employeeA']->id, $employeeIds);
        $this->assertNotContains($seed['employeeB']->id, $employeeIds);
    }

    public function test_supervisor_cannot_view_or_update_evaluation_outside_team(): void
    {
        $seed = $this->seedScopedCompany();

        $evaluation = Evaluation::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'evaluator_id' => $seed['supervisorB']->id,
            'period' => '2026-01',
            'status' => 'draft',
        ]);

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/evaluations/'.$evaluation->id);
        $show->assertForbidden();

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/evaluations/'.$evaluation->id, [
                'score' => 4.5,
            ]);
        $update->assertForbidden();
    }

    public function test_supervisor_anomalies_scoped_to_own_team(): void
    {
        $seed = $this->seedScopedCompany();

        AttendanceLog::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeA']->id,
            'date' => now()->toDateString(),
            'session_number' => 1,
            'status' => 'complete',
            'method' => 'manual',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $seed['company']->id,
            'employee_id' => $seed['employeeB']->id,
            'date' => now()->toDateString(),
            'session_number' => 1,
            'status' => 'complete',
            'method' => 'manual',
        ]);

        $token = $seed['supervisorA']->createToken('tests')->plainTextToken;

        // The `date` column is stored with a full datetime string in the
        // SQLite test schema (Eloquent's `date` cast round-trip), so an
        // inclusive upper bound of "today" excludes today's own rows in a
        // string-based BETWEEN comparison. Use tomorrow as date_to to keep
        // this test about RBAC scoping rather than date-cast precision.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/anomalies?date_to='.now()->addDay()->toDateString());

        $response->assertOk();

        $employeeIds = collect($response->json('data.items'))->pluck('employee_id')->all();
        $this->assertContains($seed['employeeA']->id, $employeeIds);
        $this->assertNotContains($seed['employeeB']->id, $employeeIds);
    }
}

<?php

namespace Tests\Feature\Security;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\AttendanceKiosk;
use App\Models\AttendanceLog;
use App\Models\BiometricEnrollmentRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Notification;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\Project;
use App\Models\SalaryAdvance;
use App\Models\Schedule;
use App\Models\Site;
use App\Models\Task;
use App\Models\UserInvitation;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TenantModelIsolationTest extends TestCase
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

    public function test_attendance_kiosk_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        AttendanceKiosk::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Kiosk A',
            'device_code' => 'CODE-A',
        ]);

        AttendanceKiosk::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Kiosk B',
            'device_code' => 'CODE-B',
        ]);

        app()->instance('current_company', $companyA);

        $kiosks = AttendanceKiosk::all();
        $kiosk = $kiosks->first();

        $this->assertCount(1, $kiosks, 'AttendanceKiosk should be isolated by company_id'); }
        $this->assertNotNull($kiosk);
        $this->assertEquals('Kiosk A', $kiosk->name);
    }

    public function test_biometric_enrollment_request_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        BiometricEnrollmentRequest::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'status' => 'pending',
        ]);

        BiometricEnrollmentRequest::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'status' => 'pending',
        ]);

        app()->instance('current_company', $companyA);

        $this->assertCount(1, BiometricEnrollmentRequest::all(), 'BiometricEnrollmentRequest should be isolated by company_id'); }
    }

    public function test_user_invitation_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        UserInvitation::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'company_id' => $companyA->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 1,
            'email' => 'a@test.com',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'mgr@test.com',
            'token_hash' => 'hash-a',
            'expires_at' => now()->addDays(1),
        ]);

        UserInvitation::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 2,
            'email' => 'b@test.com',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'mgr@test.com',
            'token_hash' => 'hash-b',
            'expires_at' => now()->addDays(1),
        ]);

        app()->instance('current_company', $companyA);

        $this->assertCount(1, UserInvitation::all(), 'UserInvitation should be isolated by company_id'); }
    }

    public function test_employee_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'emp-a@test.com',
            'password_hash' => 'secret',
            'role' => 'employee',
        ]);

        Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'emp-b@test.com',
            'password_hash' => 'secret',
            'role' => 'employee',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Employee::all(), 'Employee should be isolated by company_id'); }
    }

    public function test_absence_type_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        AbsenceType::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Type A',
            'code' => 'A',
        ]);

        AbsenceType::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Type B',
            'code' => 'B',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, AbsenceType::all(), 'AbsenceType should be isolated by company_id'); }
    }

    public function test_absence_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Absence::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'absence_type_id' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'status' => 'pending',
        ]);

        Absence::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'absence_type_id' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'status' => 'pending',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Absence::all(), 'Absence should be isolated by company_id'); }
    }

    public function test_attendance_log_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        AttendanceLog::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'date' => '2026-01-01',
            'status' => 'ontime',
        ]);

        AttendanceLog::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'date' => '2026-01-01',
            'status' => 'ontime',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, AttendanceLog::all(), 'AttendanceLog should be isolated by company_id'); }
    }

    public function test_salary_advance_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'amount' => 2000,
            'status' => 'pending',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, SalaryAdvance::all(), 'SalaryAdvance should be isolated by company_id'); }
    }

    public function test_evaluation_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Evaluation::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'evaluator_id' => 3,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        Evaluation::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'evaluator_id' => 4,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Evaluation::all(), 'Evaluation should be isolated by company_id'); }
    }

    public function test_payroll_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Payroll::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'period_month' => 1,
            'period_year' => 2026,
            'status' => 'draft',
        ]);

        Payroll::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'period_month' => 1,
            'period_year' => 2026,
            'status' => 'draft',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Payroll::all(), 'Payroll should be isolated by company_id'); }
    }

    public function test_project_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Project::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Project A',
            'created_by' => 1,
        ]);

        Project::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => 2,
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Project::all(), 'Project should be isolated by company_id'); }
    }

    public function test_task_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Task::query()->forceCreate([
            'company_id' => $companyA->id,
            'title' => 'Task A',
            'created_by' => 1,
            'due_date' => now(),
        ]);

        Task::query()->forceCreate([
            'company_id' => $companyB->id,
            'title' => 'Task B',
            'created_by' => 2,
            'due_date' => now(),
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Task::all(), 'Task should be isolated by company_id'); }
    }

    public function test_notification_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Notification::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'type' => 'test',
            'title' => 'Notif A',
            'body' => 'Body A',
        ]);

        Notification::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'type' => 'test',
            'title' => 'Notif B',
            'body' => 'Body B',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Notification::all(), 'Notification should be isolated by company_id'); }
    }

    public function test_department_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Department::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Dept A',
        ]);

        Department::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Dept B',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Department::all(), 'Department should be isolated by company_id'); }
    }

    public function test_position_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Position::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Pos A',
            'department_id' => 1,
        ]);

        Position::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Pos B',
            'department_id' => 2,
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Position::all(), 'Position should be isolated by company_id'); }
    }

    public function test_site_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Site::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Site A',
        ]);

        Site::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Site B',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Site::all(), 'Site should be isolated by company_id'); }
    }

    public function test_schedule_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A'); }
        $companyB = $this->createCompany('Company B'); }

        Schedule::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Sched A',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        Schedule::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Sched B',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        app()->instance('current_company', $companyA);
        $this->assertCount(1, Schedule::all(), 'Schedule should be isolated by company_id'); }
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(str_replace(' ', '', $name)).'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }
}

<?php

namespace Tests\Feature\Web;

use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AttendanceCorrectionAdminPagesTest extends TestCase
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

    public function test_manager_can_list_and_approve_pending_correction_from_web(): void
    {
        [$company, $manager, $employee] = $this->makeCompanyWithUsers();

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:12:00', 'UTC'),
            'requested_check_out' => Carbon::parse('2026-05-27 17:20:00', 'UTC'),
            'reason' => 'Oubli du pointage mobile',
            'status' => 'pending',
        ]);

        $index = $this->actingAs($manager, 'web')->get('/attendance-corrections');
        $index->assertOk();
        $index->assertSee($employee->last_name);
        $index->assertSee('Oubli du pointage mobile');

        $approve = $this->actingAs($manager, 'web')
            ->post("/attendance-corrections/{$correction->id}/approve");

        $approve->assertRedirect('/attendance-corrections');

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'status' => 'applied',
            'reviewed_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'method' => 'manual',
            'corrected_by' => $manager->id,
        ]);
    }

    public function test_manager_can_reject_pending_correction_from_web(): void
    {
        [, $manager, $employee] = $this->makeCompanyWithUsers();

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $manager->company_id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:12:00', 'UTC'),
            'reason' => 'Test rejet',
            'status' => 'pending',
        ]);

        $reject = $this->actingAs($manager, 'web')
            ->post("/attendance-corrections/{$correction->id}/reject");

        $reject->assertRedirect('/attendance-corrections');

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'status' => 'rejected',
            'reviewed_by' => $manager->id,
        ]);
    }

    public function test_employee_is_forbidden_from_attendance_corrections_page(): void
    {
        [, , $employee] = $this->makeCompanyWithUsers();

        $response = $this->actingAs($employee, 'web')->get('/attendance-corrections');

        $response->assertForbidden();
    }

    public function test_manager_cannot_approve_correction_from_other_company(): void
    {
        [, $manager] = $this->makeCompanyWithUsers();
        [$otherCompany, , $otherEmployee] = $this->makeCompanyWithUsers('company-b', 'b.test');

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:00:00', 'UTC'),
            'reason' => 'Tenant etranger',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager, 'web')
            ->post("/attendance-corrections/{$correction->id}/approve");

        $response->assertNotFound();
    }

    /**
     * @return array{Company, Employee, Employee}
     */
    private function makeCompanyWithUsers(string $slug = 'company-a', string $domain = 'company.test'): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Manager',
            'email' => 'manager@'.$domain,
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'salary_type' => 'daily',
            'salary_base' => 800,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@'.$domain,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'daily',
            'salary_base' => 800,
        ]);

        return [$company, $manager, $employee];
    }
}

<?php

namespace Tests\Feature\Security;

use App\Models\AttendanceKiosk;
use App\Models\BiometricEnrollmentRequest;
use App\Models\Company;
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
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

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

        $this->assertCount(1, AttendanceKiosk::all(), 'AttendanceKiosk should be isolated by company_id');
        $this->assertEquals('Kiosk A', AttendanceKiosk::first()->name);
    }

    public function test_biometric_enrollment_request_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

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

        $this->assertCount(1, BiometricEnrollmentRequest::all(), 'BiometricEnrollmentRequest should be isolated by company_id');
    }

    public function test_user_invitation_is_isolated(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

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

        $this->assertCount(1, UserInvitation::all(), 'UserInvitation should be isolated by company_id');
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
            'email' => strtolower(str_replace(' ', '', $name)) . '@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }
}

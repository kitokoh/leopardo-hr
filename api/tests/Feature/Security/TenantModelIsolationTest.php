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

    public function test_secondary_models_are_correctly_isolated_by_tenant(): void
    {
        // 1. Create two companies
        /** @var Company $companyA */
        $companyA = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'IT',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
        ]);

        /** @var Company $companyB */
        $companyB = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'IT',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
        ]);

        // 2. Create data for Company A
        app()->instance('current_company', $companyA);

        AttendanceKiosk::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Kiosk A',
            'device_code' => 'KIOSKA',
            'status' => 'active',
        ]);

        BiometricEnrollmentRequest::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'status' => 'pending',
        ]);

        UserInvitation::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyA->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 1,
            'email' => 'invited@a.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@a.test',
            'token_hash' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertCount(1, AttendanceKiosk::all());
        $this->assertCount(1, BiometricEnrollmentRequest::all());
        $this->assertCount(1, UserInvitation::all());

        // 3. Switch to Company B and create data
        app()->instance('current_company', $companyB);

        AttendanceKiosk::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Kiosk B',
            'device_code' => 'KIOSKB',
            'status' => 'active',
        ]);

        BiometricEnrollmentRequest::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'status' => 'pending',
        ]);

        UserInvitation::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 2,
            'email' => 'invited@b.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@b.test',
            'token_hash' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // 4. Verify Company B sees only its own data
        $this->assertCount(1, AttendanceKiosk::all());
        $this->assertCount(1, BiometricEnrollmentRequest::all());
        $this->assertCount(1, UserInvitation::all());

        $this->assertEquals('Kiosk B', AttendanceKiosk::first()->name);
        $this->assertEquals(2, BiometricEnrollmentRequest::first()->employee_id);
        $this->assertEquals('invited@b.test', UserInvitation::first()->email);

        // 5. Switch back to Company A and verify it sees only its own data
        app()->instance('current_company', $companyA);

        $this->assertCount(1, AttendanceKiosk::all());
        $this->assertCount(1, BiometricEnrollmentRequest::all());
        $this->assertCount(1, UserInvitation::all());

        $this->assertEquals('Kiosk A', AttendanceKiosk::first()->name);
        $this->assertEquals(1, BiometricEnrollmentRequest::first()->employee_id);
        $this->assertEquals('invited@a.test', UserInvitation::first()->email);

        // 6. Verify global scope bypass
        $this->assertCount(2, AttendanceKiosk::withoutGlobalScopes()->get());
        $this->assertCount(2, BiometricEnrollmentRequest::withoutGlobalScopes()->get());
        $this->assertCount(2, UserInvitation::withoutGlobalScopes()->get());
    }
}

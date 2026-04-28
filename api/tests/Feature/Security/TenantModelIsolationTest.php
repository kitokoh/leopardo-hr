<?php

namespace Tests\Feature\Security;

use App\Models\BiometricEnrollmentRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\UserInvitation;
use App\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
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

    /**
     * @test
     * Ce test verifie que le modele UserInvitation est protege par l'isolation multi-tenant.
     * Si le trait BelongsToCompany est absent, ce test echouera car il verra les donnees des autres tenants.
     */
    public function test_user_invitation_model_is_isolated_by_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        // On cree une invitation pour la Company B
        DB::table('user_invitations')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 1,
            'email' => 'invited@b.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@b.test',
            'token_hash' => hash('sha256', 'token-b'),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // On bascule le contexte sur la Company A
        app()->instance('current_company', $companyA);

        // L'invitation de B ne doit PAS etre visible pour A via le modele Eloquent
        $this->assertCount(0, UserInvitation::all(), 'Company A should not see Company B invitations via Eloquent global scope');

        // On cree une invitation pour la Company A
        UserInvitation::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'company_id' => $companyA->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 2,
            'email' => 'invited@a.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@a.test',
            'token_hash' => hash('sha256', 'token-a'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertCount(1, UserInvitation::all());
        $this->assertEquals('invited@a.test', UserInvitation::first()->email);

        // Verification brute en base (hors scope)
        $this->assertEquals(2, DB::table('user_invitations')->count());
    }

    /**
     * @test
     * Verifie l'isolation pour BiometricEnrollmentRequest.
     */
    public function test_biometric_enrollment_request_model_is_isolated_by_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        DB::table('biometric_enrollment_requests')->insert([
            'company_id' => $companyB->id,
            'employee_id' => 1,
            'status' => 'pending',
            'request_source' => 'mobile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app()->instance('current_company', $companyA);

        $this->assertCount(0, BiometricEnrollmentRequest::all(), 'Company A should not see Company B biometric requests');
    }

    /**
     * @test
     * Verifie l'isolation pour Employee (qui possede deja le trait BelongsToCompany).
     * Ce test doit PASSER.
     */
    public function test_employee_model_is_isolated_by_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => 'hash',
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $companyA);

        $this->assertCount(0, Employee::all(), 'Company A should not see Company B employees');
    }

    /**
     * @test
     * Verifie l'isolation pour AttendanceKiosk.
     */
    public function test_attendance_kiosk_model_is_isolated_by_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        DB::table('attendance_kiosks')->insert([
            'company_id' => $companyB->id,
            'name' => 'Kiosk B',
            'device_code' => 'KIOSK-B',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app()->instance('current_company', $companyA);

        $this->assertCount(0, AttendanceKiosk::all(), 'Company A should not see Company B kiosks');
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => Str::slug($name) . '@test.local',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }
}

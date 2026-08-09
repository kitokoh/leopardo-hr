<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-QA-003 — API contracts per profile (employee/manager/superadmin/kiosk).
 *
 * Every surface has its own auth guard (Sanctum employee tokens, the
 * super_admin_api guard, and the kiosk device X-Kiosk-Token header) and each
 * guard must fail the same predictable way: unauthenticated callers get 401,
 * wrong-role callers get 403, and every error payload keeps the stable
 * `error`/`message` contract the frontends rely on. This test pins that
 * behaviour so a future refactor of any guard cannot silently change status
 * codes or drop the error envelope for one profile while leaving the others
 * untouched.
 */
class ProfilePermissionErrorContractTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_employee_surface_rejects_unauthenticated_requests_with_stable_401_envelope(): void
    {
        $response = $this->getJson('/api/v1/employees');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_employee_role_is_forbidden_from_manager_only_employee_list(): void
    {
        [, , $employee] = $this->actors();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/employees')
            ->assertStatus(403);
    }

    public function test_employee_role_cannot_view_a_coworkers_profile(): void
    {
        [, $manager, $employee] = $this->actors();

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$manager->id}")
            ->assertStatus(403);
    }

    public function test_manager_role_can_reach_manager_only_employee_list(): void
    {
        [, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/employees')
            ->assertOk();
    }

    public function test_manager_role_gets_not_found_envelope_for_missing_employee_not_forbidden(): void
    {
        [, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/employees/999999')
            ->assertNotFound()
            ->assertJsonStructure(['error', 'message', 'localized_message'])
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');
    }

    public function test_manager_role_gets_not_found_not_forbidden_when_archiving_across_tenants(): void
    {
        [, $managerA] = $this->actors();
        [, $managerB] = $this->actors('B');

        Sanctum::actingAs($managerA);

        // The tenant-scoped BelongsToCompany global scope on Employee makes a
        // cross-tenant row invisible to findOrFail() before the archive policy
        // even runs. That is the correct, more secure contract: a manager must
        // not be able to tell "exists in another tenant" (403) apart from
        // "does not exist" (404) by probing another company's employee ids.
        $this->postJson("/api/v1/employees/{$managerB->id}/archive")
            ->assertNotFound()
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');
    }

    public function test_superadmin_surface_rejects_unauthenticated_requests_with_401(): void
    {
        $this->getJson('/api/v1/platform/auth/me')
            ->assertUnauthorized();
    }

    public function test_superadmin_surface_rejects_a_tenant_employee_token_with_401(): void
    {
        [, $manager] = $this->actors();
        $employeeToken = $manager->createToken('tests')->plainTextToken;

        // A tenant Sanctum token is not a super_admin_api token: the
        // platform guard must reject it, not silently authenticate it.
        $this->withHeader('Authorization', "Bearer {$employeeToken}")
            ->getJson('/api/v1/platform/auth/me')
            ->assertUnauthorized();
    }

    public function test_superadmin_can_reach_platform_only_surface(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Ops Super Admin',
            'email' => 'ops-admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ops-admin@leopardo.test')
            ->assertJsonPath('data.role', 'super_admin');
    }

    public function test_kiosk_surface_rejects_missing_token_with_401_and_stable_message(): void
    {
        [$company] = $this->actors();
        $kiosk = $this->registerKiosk($company);

        $this->getJson("/api/v1/kiosks/{$kiosk->device_code}/roster")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'INVALID_KIOSK_TOKEN');
    }

    public function test_kiosk_surface_rejects_wrong_token_with_401_and_stable_message(): void
    {
        [$company] = $this->actors();
        $kiosk = $this->registerKiosk($company);

        $this->withHeader('X-Kiosk-Token', 'not-the-real-token')
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/roster")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'INVALID_KIOSK_TOKEN');
    }

    public function test_kiosk_surface_rejects_an_employee_sanctum_token_it_does_not_understand(): void
    {
        [$company, $manager] = $this->actors();
        $kiosk = $this->registerKiosk($company);
        $employeeToken = $manager->createToken('tests')->plainTextToken;

        // The kiosk endpoints only understand X-Kiosk-Token; a Sanctum
        // bearer token from a real employee must not be accepted either.
        $this->withHeader('Authorization', "Bearer {$employeeToken}")
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/roster")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'INVALID_KIOSK_TOKEN');
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(string $suffix = 'A'): array
    {
        $company = Company::query()->create([
            'name' => "Contract Co {$suffix}",
            'slug' => 'contract-co-'.Str::lower($suffix).'-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => "contract-{$suffix}@company.test",
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => $suffix,
            'email' => "manager-{$suffix}@company.test",
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Employee',
            'last_name' => $suffix,
            'email' => "employee-{$suffix}@company.test",
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    private function registerKiosk(Company $company): AttendanceKiosk
    {
        return AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Front Desk Kiosk',
            'biometric_mode' => 'fingerprint',
            'device_code' => strtoupper(Str::random(10)),
            'sync_token_hash' => Hash::make('a-real-sync-token'),
            'status' => 'active',
        ]);
    }
}

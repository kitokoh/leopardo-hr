<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Tests for the HR Mobile App dedicated routes.
 *
 * Covers:
 * - RH employee can access /api/v1/hr/** endpoints
 * - Regular employee CANNOT access /api/v1/hr/** (403)
 * - Principal (manager) CAN access /api/v1/hr/** (inherited access)
 * - Marketing/comptable manager CANNOT access /api/v1/hr/** (403)
 * - HR CANNOT set manager_role when adding an employee via /hr/employees
 */
class HrAppRoutesTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;
    private Employee $principal;
    private Employee $hrManager;
    private Employee $regularEmployee;
    private Employee $marketingManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $this->company = Company::query()->create([
            'name'         => 'Test Company',
            'slug'         => 'test-company',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'company@test.hr',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);

        $base = [
            'company_id'    => $this->company->id,
            'password_hash' => Hash::make('password123'),
            'status'        => 'active',
            'first_name'    => 'Test',
            'last_name'     => 'User',
            'gender'        => 'M',
            'date_of_birth' => '1990-01-01',
            'contract_type' => 'cdi',
            'contract_start'=> '2023-01-01',
            'salary_type'   => 'monthly',
        ];

        $this->principal = Employee::query()->create(array_merge($base, [
            'email'        => 'principal@test.hr',
            'role'         => 'manager',
            'manager_role' => 'principal',
        ]));

        $this->hrManager = Employee::query()->create(array_merge($base, [
            'email'        => 'rh@test.hr',
            'role'         => 'manager',
            'manager_role' => 'rh',
        ]));

        $this->regularEmployee = Employee::query()->create(array_merge($base, [
            'email'        => 'employee@test.hr',
            'role'         => 'employee',
            'manager_role' => null,
        ]));

        $this->marketingManager = Employee::query()->create(array_merge($base, [
            'email'        => 'marketing@test.hr',
            'role'         => 'manager',
            'manager_role' => 'marketing',
        ]));
    }

    // ── GET /api/v1/hr/dashboard ──────────────────────────────────────────────

    public function test_rh_manager_can_access_hr_dashboard(): void
    {
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['total_active_employees', 'total_employees', 'pending_invitations', 'new_this_month'],
            'meta' => ['app', 'role'],
        ]);
        $response->assertJsonPath('meta.app', 'rh');
        $response->assertJsonPath('meta.role', 'rh');
    }

    public function test_principal_can_access_hr_dashboard(): void
    {
        $response = $this->actingAs($this->principal, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertOk();
        $response->assertJsonPath('meta.role', 'principal');
    }

    public function test_regular_employee_cannot_access_hr_dashboard(): void
    {
        $response = $this->actingAs($this->regularEmployee, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertForbidden();
        $response->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_marketing_manager_cannot_access_hr_dashboard(): void
    {
        $response = $this->actingAs($this->marketingManager, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertForbidden();
        $response->assertJsonPath('error', 'INSUFFICIENT_ROLE');
    }

    // ── GET /api/v1/hr/me ─────────────────────────────────────────────────────

    public function test_rh_me_returns_correct_app_context(): void
    {
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/hr/me');

        $response->assertOk();
        $response->assertJsonPath('data.app', 'rh');
        $response->assertJsonPath('data.role_label', 'Responsable RH');
        $response->assertJsonPath('data.manager_role', 'rh');
    }

    // ── GET /api/v1/hr/employees ──────────────────────────────────────────────

    public function test_rh_can_list_employees(): void
    {
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/hr/employees');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta']);
    }

    // ── POST /api/v1/hr/employees ─────────────────────────────────────────────

    public function test_rh_can_add_regular_employee(): void
    {
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/hr/employees', [
                'first_name'     => 'Jean',
                'last_name'      => 'Dupont',
                'email'          => 'jean.dupont@test.hr',
                'gender'         => 'M',
                'date_of_birth'  => '1995-06-15',
                'contract_type'  => 'cdi',
                'contract_start' => '2026-01-01',
                'salary_type'    => 'monthly',
                'salary_base'    => 50000,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'employee');
    }

    public function test_rh_cannot_create_employee_with_manager_role(): void
    {
        // Even if HR sends manager_role in the payload, it should be ignored
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/hr/employees', [
                'first_name'     => 'Evil',
                'last_name'      => 'Hr',
                'email'          => 'evil.hr@test.hr',
                'gender'         => 'M',
                'date_of_birth'  => '1990-01-01',
                'contract_type'  => 'cdi',
                'contract_start' => '2026-01-01',
                'salary_type'    => 'monthly',
                'role'           => 'manager',         // should be stripped
                'manager_role'   => 'comptable',       // should be stripped
            ]);

        $response->assertCreated();
        // Role must always be 'employee' regardless of input
        $response->assertJsonPath('data.role', 'employee');
        $this->assertDatabaseHas('employees', [
            'email'        => 'evil.hr@test.hr',
            'role'         => 'employee',
            'manager_role' => null,
        ]);
    }

    public function test_regular_employee_cannot_add_employees(): void
    {
        $response = $this->actingAs($this->regularEmployee, 'sanctum')
            ->postJson('/api/v1/hr/employees', [
                'first_name'     => 'Test',
                'last_name'      => 'Employee',
                'email'          => 'test@test.hr',
                'gender'         => 'M',
                'date_of_birth'  => '1990-01-01',
                'contract_type'  => 'cdi',
                'contract_start' => '2026-01-01',
                'salary_type'    => 'monthly',
            ]);

        $response->assertForbidden();
    }

    // ── GET /api/v1/hr/team-overview ──────────────────────────────────────────

    public function test_rh_can_get_team_overview(): void
    {
        $response = $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/hr/team-overview');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['total'],
        ]);
    }
}

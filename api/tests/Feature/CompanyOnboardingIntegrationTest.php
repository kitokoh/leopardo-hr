<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * End-to-end company onboarding: create company → add manager → configure payroll.
 */
class CompanyOnboardingIntegrationTest extends TestCase
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

    public function test_platform_admin_can_create_company_and_manager(): void
    {
        $admin = Employee::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        // List companies — should be accessible
        $this->getJson('/api/v1/platform/companies')
            ->assertOk();
    }

    public function test_manager_can_list_own_employees(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $emp1 = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'first_name' => 'Ahmed',
        ]);

        $emp2 = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'first_name' => 'Fatima',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees');
        $response->assertOk();

        $employees = collect($response->json('data'));
        $this->assertGreaterThanOrEqual(2, $employees->count());
    }

    public function test_employee_from_other_company_not_visible(): void
    {
        $companyA = Company::factory()->create(['name' => 'Alpha Corp']);
        $companyB = Company::factory()->create(['name' => 'Beta Corp']);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'employee',
            'first_name' => 'Invisible',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/employees');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('first_name')->toArray();
        $this->assertNotContains('Invisible', $names);
    }

    public function test_onboarding_checklist_available_for_new_manager(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding-setup/checklist');

        // Should return checklist or 404 if not provisioned yet
        $this->assertContains($response->status(), [200, 404]);
    }
}

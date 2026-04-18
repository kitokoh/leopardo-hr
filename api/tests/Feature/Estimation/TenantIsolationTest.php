<?php

namespace Tests\Feature\Estimation;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
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

    public function test_manager_cannot_access_foreign_tenant_estimation_endpoints(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 50,
        ]);

        Sanctum::actingAs($managerA);

        $summary = $this->getJson("/api/v1/employees/{$employeeB->id}/daily-summary?date=2026-04-18");
        $summary->assertStatus(404);
        $summary->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        $estimate = $this->getJson("/api/v1/employees/{$employeeB->id}/quick-estimate?from=2026-04-01&to=2026-04-18");
        $estimate->assertStatus(404);
        $estimate->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        $receipt = $this->get("/api/v1/employees/{$employeeB->id}/receipt?from=2026-04-01&to=2026-04-18");
        $receipt->assertStatus(404);
    }
}

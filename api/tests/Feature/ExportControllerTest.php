<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ExportControllerTest extends TestCase
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

    public function test_manager_can_export_employees(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Employee::factory()->count(3)->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/export/employees?format=csv');
        $response->assertOk();
    }

    public function test_employee_cannot_export(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/export/employees?format=csv')->assertStatus(403);
    }

    public function test_manager_can_export_attendance(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/export/attendance?format=csv');
        $response->assertOk();
    }
}

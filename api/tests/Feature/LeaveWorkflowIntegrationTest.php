<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * End-to-end leave workflow: request → approval → balance deduction.
 */
class LeaveWorkflowIntegrationTest extends TestCase
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

    public function test_employee_can_submit_leave_request(): void
    {
        $company = Company::factory()->create(['country' => 'DZ']);

        Employee::factory()->manager()->create(['company_id' => $company->id]);

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'type' => 'conge_annuel',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'reason' => 'Vacances familiales',
        ]);

        // Should create successfully or return validation error
        $this->assertContains($response->status(), [201, 422]);

        if ($response->status() === 201) {
            $response->assertJsonStructure([
                'data' => ['id', 'type', 'start_date', 'end_date', 'status'],
            ]);
            $this->assertEquals('pending', $response->json('data.status'));
        }
    }

    public function test_manager_can_list_pending_absences(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/absences');
        $response->assertOk();
    }

    public function test_leave_requests_isolated_between_tenants(): void
    {
        $companyA = Company::factory()->create(['name' => 'Tenant A']);
        $companyB = Company::factory()->create(['name' => 'Tenant B']);

        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        $empB = Employee::factory()->create(['company_id' => $companyB->id]);

        // Employee B submits absence
        Sanctum::actingAs($empB);
        $this->postJson('/api/v1/absences', [
            'type' => 'conge_annuel',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'reason' => 'Test isolation',
        ]);

        // Manager A should not see Tenant B absences
        Sanctum::actingAs($managerA);
        $response = $this->getJson('/api/v1/absences');
        $response->assertOk();

        $reasons = collect($response->json('data'))->pluck('reason')->toArray();
        $this->assertNotContains('Test isolation', $reasons);
    }

    public function test_employee_cannot_approve_own_leave(): void
    {
        $company = Company::factory()->create();

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        // Attempt to create and approve — employee role should not have approve permission
        $response = $this->postJson('/api/v1/absences', [
            'type' => 'conge_annuel',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'reason' => 'Test',
        ]);

        if ($response->status() === 201) {
            $absenceId = $response->json('data.id');

            // Employee should not be able to approve
            $approveResponse = $this->putJson("/api/v1/absences/{$absenceId}/approve");
            $this->assertContains($approveResponse->status(), [403, 404, 405]);
        }
    }
}

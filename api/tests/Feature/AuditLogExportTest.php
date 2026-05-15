<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuditLogExportTest extends TestCase
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

    public function test_export_csv_returns_csv_for_principal_manager(): void
    {
        $manager = $this->employee([
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        AuditLog::create([
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'created',
            'auditable_type' => 'Employee',
            'auditable_id' => $manager->id,
            'new_values' => ['first_name' => 'Test'],
        ]);

        $response = $this->get('/api/v1/audit-logs/export-csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_csv_forbidden_for_non_principal(): void
    {
        $employee = $this->employee([
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $response = $this->get('/api/v1/audit-logs/export-csv');

        $response->assertForbidden();
    }

    public function test_export_csv_with_date_filter(): void
    {
        $manager = $this->employee([
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/v1/audit-logs/export-csv?from=2026-01-01&to=2026-12-31');

        $response->assertOk();
    }

    public function test_audit_log_index_returns_paginated_list(): void
    {
        $manager = $this->employee([
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        AuditLog::create([
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'updated',
            'auditable_type' => 'Employee',
            'auditable_id' => $manager->id,
        ]);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_audit_log_index_filtered_by_action(): void
    {
        $manager = $this->employee([
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        AuditLog::create([
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'deleted',
            'auditable_type' => 'Employee',
            'auditable_id' => 1,
        ]);

        $response = $this->getJson('/api/v1/audit-logs?action=deleted');

        $response->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): Employee
    {
        $company = Company::factory()->create();

        return Employee::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $attributes));
    }
}

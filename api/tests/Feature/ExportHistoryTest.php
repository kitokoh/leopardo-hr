<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2199 — GET /export/history renvoyait un stub `data: []` en dur.
 * L'historique s'appuie désormais sur la piste d'audit réelle
 * (audit_logs + DataAccessAuditLogger::recordSensitive), paginé et
 * tenant-scope.
 */
class ExportHistoryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_history_lists_real_export_audit_rows(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Manager',
        ]);

        Employee::factory()->count(2)->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        // Un export réel déclenche une ligne d'audit sensible.
        $this->getJson('/api/v1/export/employees?format=csv')->assertOk();

        $response = $this->getJson('/api/v1/export/history');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'employees')
            ->assertJsonPath('data.0.format', 'csv')
            ->assertJsonPath('data.0.requested_by', 'Nadia Manager')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_history_is_isolated_between_tenants(): void
    {
        $companyA = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $companyB = Company::factory()->create();
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Employee::factory()->count(1)->create(['company_id' => $companyA->id]);

        Sanctum::actingAs($managerA);
        $this->getJson('/api/v1/export/employees')->assertOk();

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/export/history')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_history_paginates(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        // 5 lignes d'audit directes (3 exports + 2 comptables) pour le tenant.
        foreach (['hr.export.employees', 'hr.export.attendance', 'hr.export.vehicles', 'payroll.accounting_export', 'hr.export.contracts'] as $i => $resource) {
            AuditLog::query()->create([
                'company_id' => $company->id,
                'user_id' => $manager->id,
                'action' => 'sensitive_data_access',
                'metadata' => ['resource' => $resource, 'report' => 'report_'.$i, 'format' => 'csv'],
            ]);
        }

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/export/history?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_history_requires_manager(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/export/history')->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-MOB-007 — Gestion RH mobile: nommer/revoquer RH, permissions, audit.
 *
 * The mobile "Team" screen nominates/revokes HR through the generic
 * PATCH /employees/{id} endpoint, while the legacy web dashboard uses the
 * dedicated POST /employees/{id}/assign-role endpoint. Both paths must
 * leave an immutable audit trail entry so a principal manager can review
 * every permission change on their team.
 */
class RoleAssignmentAuditTest extends TestCase
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

    public function test_assign_role_endpoint_writes_an_audit_log_entry(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/assign-role", [
            'manager_role' => 'rh',
        ]);

        $response->assertOk();

        $log = AuditLog::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->where('action', 'role_assigned')
            ->first();

        $this->assertNotNull($log, 'Expected a role_assigned audit log entry.');
        $this->assertSame($principal->id, $log->user_id);
        $this->assertArrayHasKey('manager_role', $log->old_values);
        $this->assertNull($log->old_values['manager_role']);
        $this->assertSame('rh', $log->new_values['manager_role'] ?? null);
    }

    public function test_revoking_a_role_via_assign_role_endpoint_writes_an_audit_log_entry(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $hrEmployee = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $this->postJson("/api/v1/employees/{$hrEmployee->id}/assign-role", [
            'manager_role' => null,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('auditable_type', $hrEmployee->getMorphClass())
            ->where('auditable_id', $hrEmployee->id)
            ->where('action', 'role_revoked')
            ->first();

        $this->assertNotNull($log, 'Expected a role_revoked audit log entry.');
        $this->assertSame('rh', $log->old_values['manager_role'] ?? null);
        $this->assertArrayHasKey('manager_role', $log->new_values);
        $this->assertNull($log->new_values['manager_role']);
    }

    public function test_nominating_hr_via_the_generic_employee_update_endpoint_also_writes_an_audit_log_entry(): void
    {
        // This mirrors the manager mobile app's TeamScreen._toggleHrRole(),
        // which patches role/manager_role via PATCH /employees/{id} instead
        // of the dedicated assign-role endpoint.
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $response = $this->patchJson("/api/v1/employees/{$employee->id}", [
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $response->assertOk();

        $log = AuditLog::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->where('action', 'role_assigned')
            ->first();

        $this->assertNotNull($log, 'Expected a role_assigned audit log entry from the generic update endpoint.');
        $this->assertSame($principal->id, $log->user_id);
        $this->assertSame('rh', $log->new_values['manager_role'] ?? null);
    }

    public function test_revoking_hr_via_the_generic_employee_update_endpoint_also_writes_an_audit_log_entry(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $hrEmployee = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $this->patchJson("/api/v1/employees/{$hrEmployee->id}", [
            'role' => 'employee',
            'manager_role' => null,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('auditable_type', $hrEmployee->getMorphClass())
            ->where('auditable_id', $hrEmployee->id)
            ->where('action', 'role_revoked')
            ->first();

        $this->assertNotNull($log, 'Expected a role_revoked audit log entry from the generic update endpoint.');
        $this->assertSame('rh', $log->old_values['manager_role'] ?? null);
    }

    public function test_updating_unrelated_fields_does_not_create_a_role_audit_log_entry(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $this->patchJson("/api/v1/employees/{$employee->id}", [
            'phone' => '+213555000111',
        ])->assertOk();

        $count = AuditLog::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->whereIn('action', ['role_assigned', 'role_revoked'])
            ->count();

        $this->assertSame(0, $count);
    }

    public function test_a_non_principal_manager_cannot_assign_roles_and_no_audit_log_is_written(): void
    {
        $company = Company::factory()->create();
        $deptManager = Employee::factory()->managerDept()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($deptManager);

        $this->patchJson("/api/v1/employees/{$employee->id}", [
            'role' => 'manager',
            'manager_role' => 'rh',
        ])->assertUnprocessable();

        $count = AuditLog::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->whereIn('action', ['role_assigned', 'role_revoked'])
            ->count();

        $this->assertSame(0, $count);
    }
}

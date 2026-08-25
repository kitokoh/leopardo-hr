<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #5439 — journal d'audit global : écriture unifiée (AuditLog::record),
 * lecture RBAC manager RH/principal, isolation tenant, rétention RGPD
 * configurable par entreprise (audit:purge).
 */
class AuditLogGlobalTest extends TestCase
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
     * @param  array<string, mixed>  $attrs
     */
    private function principal(array $attrs = []): Employee
    {
        return $this->employee(array_merge([
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ], $attrs));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): Employee
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'UTC']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $attributes));

        return $employee;
    }

    // ── Écriture : AuditLog::record ─────────────────────────────────────────

    public function test_record_helper_writes_module_request_id_and_company(): void
    {
        $principal = $this->principal();

        AuditLog::record(
            'planning',
            'planning.absence.approve',
            null,
            $principal,
            ['status' => 'pending'],
            ['status' => 'approved'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $principal->company_id,
            'user_id' => $principal->id,
            'module' => 'planning',
            'action' => 'planning.absence.approve',
        ]);

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('planning', $log->module);
        $this->assertNotEmpty($log->request_id);
        $this->assertSame(['status' => 'approved'], $log->new_values);
    }

    public function test_record_helper_resolves_company_from_subject_when_actor_null(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        AuditLog::record('hr', 'hr.departure.register', $employee, null);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'module' => 'hr',
            'auditable_type' => $employee->getMorphClass(),
            'auditable_id' => $employee->id,
        ]);
    }

    // ── Lecture : RBAC + filtres + isolation ────────────────────────────────

    public function test_principal_manager_can_list_audit_logs(): void
    {
        $principal = $this->principal();
        Sanctum::actingAs($principal);

        AuditLog::record('payroll', 'payroll.validate', null, $principal);

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.module', 'payroll');
    }

    public function test_rh_manager_can_list_audit_logs(): void
    {
        $rh = $this->employee([
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);
        Sanctum::actingAs($rh);

        $this->getJson('/api/v1/audit-logs')->assertOk();
    }

    public function test_employee_is_forbidden(): void
    {
        $employee = $this->employee(['role' => 'employee', 'status' => 'active']);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_module_filter(): void
    {
        $principal = $this->principal();
        Sanctum::actingAs($principal);

        AuditLog::record('payroll', 'payroll.validate', null, $principal);
        AuditLog::record('planning', 'planning.absence.approve', null, $principal);

        $this->getJson('/api/v1/audit-logs?module=planning')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.module', 'planning');
    }

    public function test_show_isolation_tenant_cross_company_404(): void
    {
        $principal = $this->principal();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['timezone' => 'UTC']);
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);

        $foreign = AuditLog::record('hr', 'hr.departure.register', $otherEmployee, null);

        Sanctum::actingAs($principal);

        $this->getJson("/api/v1/audit-logs/{$foreign->id}")->assertNotFound();
    }

    public function test_show_returns_own_company_log(): void
    {
        $principal = $this->principal();
        $log = AuditLog::record('auth', 'auth.token.revoked', null, $principal);

        Sanctum::actingAs($principal);

        $this->getJson("/api/v1/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.module', 'auth');
    }

    // ── Rétention RGPD : audit:purge par entreprise ─────────────────────────

    public function test_purge_respects_company_retention_and_journalizes(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'UTC']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        // Rétention courte pour ce tenant (1 mois).
        CompanySetting::query()->create([
            'key' => 'audit_retention_months',
            'value' => '1',
            'value_type' => 'int',
        ]);

        $old = AuditLog::record('auth', 'auth.token.revoked', null, $employee);
        AuditLog::query()->where('id', $old->id)->update(['created_at' => now()->subMonths(3)]);
        $fresh = AuditLog::record('auth', 'auth.token.revoked', null, $employee);

        $exitCode = Artisan::call('audit:purge');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $fresh->id]);

        // La purge est journalisée (action audit.purge) — traçabilité RGPD.
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'audit',
            'action' => 'audit.purge',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Domain\Models\PlatformImpersonationSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-ADM-006 — Secure super-admin impersonation ("log in as this employee"):
 * mandatory reason, hard time limit, fully audited start/end.
 */
class PlatformImpersonationControllerTest extends TestCase
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

    private function actingAsSuperAdmin(): SuperAdmin
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    public function test_super_admin_can_start_an_impersonation_session(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        $response = $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'Investigating a payroll export bug reported by the client.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['token', 'token_type', 'expires_at']);

        $this->assertDatabaseHas('platform_impersonation_sessions', [
            'super_admin_id' => $admin->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // The minted token authenticates as the impersonated employee.
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me');
        $meResponse->assertOk()->assertJsonPath('data.id', $employee->id);
    }

    public function test_reason_is_mandatory_and_validated(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'hi',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_cannot_impersonate_an_employee_of_a_suspended_company(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::factory()->create(['status' => 'suspended']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'Support investigation for suspended tenant.',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('platform_impersonation_sessions', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_cannot_impersonate_an_archived_employee(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'archived']);

        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'Support investigation for archived employee.',
        ])->assertStatus(403);
    }

    public function test_tenant_employee_cannot_start_impersonation_sessions(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $target = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $target->id,
            'reason' => 'Should be rejected.',
        ])->assertUnauthorized();
    }

    public function test_super_admin_can_end_a_session_and_the_token_stops_working(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        $startResponse = $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'Reproducing a reported bug for this employee.',
        ])->assertCreated();

        $sessionId = $startResponse->json('data.id');
        $token = $startResponse->json('token');

        $this->deleteJson("/api/v1/platform/impersonations/{$sessionId}")->assertOk();

        $session = PlatformImpersonationSession::find($sessionId);
        $this->assertNotNull($session->ended_at);
        $this->assertSame($admin->id, $session->ended_by);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_index_lists_sessions_with_active_only_filter(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        $startResponse = $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'reason' => 'First session for the index listing test.',
        ])->assertCreated();

        $sessionId = $startResponse->json('data.id');
        $this->deleteJson("/api/v1/platform/impersonations/{$sessionId}")->assertOk();

        $secondEmployee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->postJson('/api/v1/platform/impersonations', [
            'company_id' => $company->id,
            'employee_id' => $secondEmployee->id,
            'reason' => 'Second, still-active session for the index listing test.',
        ])->assertCreated();

        $all = $this->getJson('/api/v1/platform/impersonations')->assertOk();
        $this->assertCount(2, $all->json('data'));

        $activeOnly = $this->getJson('/api/v1/platform/impersonations?active_only=1')->assertOk();
        $this->assertCount(1, $activeOnly->json('data'));
        $this->assertSame($secondEmployee->id, $activeOnly->json('data.0.employee_id'));
    }
}

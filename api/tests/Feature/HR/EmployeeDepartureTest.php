<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\EmployeeDeparture;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Workflow de départ / offboarding (issue #5324).
 *
 * Enregistrement formel du départ (dossier employee_departures + statut
 * employé `departed` + révocation d'accès), RBAC principal/rh, isolation
 * tenant (403 fail-closed), self-service.
 */
class EmployeeDepartureTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_principal_can_register_departure(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $employee->createToken('app');

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'resignation',
            'reason' => 'Opportunité externe',
            'last_work_day' => now()->addDays(30)->toDateString(),
            'notice_served' => true,
            'notice_days_served' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.departure_type', 'resignation')
            ->assertJsonPath('data.employee_status', 'departed')
            ->assertJsonPath('data.notice_served', true);

        $this->assertDatabaseHas('employee_departures', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'departure_type' => 'resignation',
        ]);

        // Statut + révocation d'accès (fail-closed : AuthController refuse
        // tout status ≠ active ; tokens Sanctum supprimés comme à l'archive).
        $this->assertSame('departed', $employee->fresh()->status);
        $this->assertSame(0, $employee->fresh()->tokens()->count());
    }

    public function test_rh_can_register_departure(): void
    {
        [$company, , $employee] = $this->createActors();
        $rh = $this->createEmployee($company, 'rh.dep@a.test', 'manager', 'rh');

        Sanctum::actingAs($rh);

        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'retirement',
            'last_work_day' => now()->addDays(15)->toDateString(),
        ])->assertStatus(201)
            ->assertJsonPath('data.departure_type', 'retirement');
    }

    public function test_dept_manager_cannot_register_departure(): void
    {
        [$company, , $employee] = $this->createActors();
        $deptManager = $this->createEmployee($company, 'dept.dep@a.test', 'manager', 'dept');

        Sanctum::actingAs($deptManager);

        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'termination',
            'last_work_day' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_employee_cannot_register_departure(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        // Ni pour soi-même (policy departure : jamais soi-même)…
        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'resignation',
            'last_work_day' => now()->addDays(5)->toDateString(),
        ])->assertForbidden();

        // …ni pour un collègue (pas de rôle manager).
        [, , $otherEmployee] = $this->createActors('other');
        $this->postJson("/api/v1/employees/{$otherEmployee->id}/departure", [
            'departure_type' => 'resignation',
            'last_work_day' => now()->addDays(5)->toDateString(),
        ])->assertForbidden();
    }

    public function test_cross_tenant_departure_is_forbidden(): void
    {
        [$companyA, $managerA] = $this->createActors();
        [$companyB, , $employeeB] = $this->createActors('company-b');

        Sanctum::actingAs($managerA);

        // Convention repo (EvaluationSecurityTest) : sans current_company
        // liée en test, le scope global est inactif → la POLICY refuse →
        // 403 fail-closed (jamais de fuite cross-tenant).
        $this->postJson("/api/v1/employees/{$employeeB->id}/departure", [
            'departure_type' => 'termination',
            'last_work_day' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_departure_rejects_invalid_type(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'mutation',
            'last_work_day' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_already_departed_employee_rejected(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);

        $payload = ['departure_type' => 'end_of_contract', 'last_work_day' => now()->addDays(7)->toDateString()];

        $this->postJson("/api/v1/employees/{$employee->id}/departure", $payload)->assertStatus(201);
        $this->postJson("/api/v1/employees/{$employee->id}/departure", $payload)->assertStatus(422);

        $this->assertSame(1, EmployeeDeparture::query()->where('employee_id', $employee->id)->count());
    }

    public function test_departed_employee_cannot_login(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        $employee->createToken('app');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'termination',
            'last_work_day' => now()->toDateString(),
        ])->assertStatus(201);

        // Tokens révoqués → le client mobile perd l'accès immédiatement.
        $this->assertSame(0, $employee->fresh()->tokens()->count());
        $this->assertSame('departed', $employee->fresh()->status);
    }

    public function test_employee_can_view_own_departure(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'resignation',
            'last_work_day' => now()->addDays(10)->toDateString(),
        ])->assertStatus(201);

        // Self-service : l'employé voit son propre enregistrement.
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/me/departure')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.departure_type', 'resignation');
    }

    public function test_manager_sees_employee_departure(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/employees/{$employee->id}/departure", [
            'departure_type' => 'retirement',
            'last_work_day' => now()->addDays(3)->toDateString(),
        ])->assertStatus(201);

        $this->getJson("/api/v1/employees/{$employee->id}/departure")
            ->assertOk()
            ->assertJsonPath('data.departure_type', 'retirement')
            ->assertJsonPath('data.employee_status', 'departed');
    }

    public function test_departure_absent_returns_null(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{Company, Employee, Employee}
     */
    private function createActors(string $suffix = 'a'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, 'manager.'.$suffix.'@dep.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.'.$suffix.'@dep.test', 'employee', null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }
}

<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\CareerEvent;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Position;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Plans de carrière (issue #5259) — événements de carrière : promotion,
 * augmentation, transfert, changement de poste.
 *
 * Workflow pending → approved → applied (ou rejected) ; `apply` met à jour
 * l'employé (position/département/salaire de base → impact paie).
 * RBAC aligné EvaluationPolicy (PA2-SEC-002/003) + isolation tenant.
 */
class CareerEventTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_can_create_promotion_event_with_snapshot(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();
        $department = $this->createDepartment($company, 'Operations', $manager);
        $position = $this->createPosition($company, 'Chef de cuisine', $department);

        $employee->forceFill([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'salary_base' => 80000,
        ])->save();

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/career-events', [
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'to_position_id' => $position->id,
            'to_salary' => 100000,
            'effective_date' => '2026-09-01',
            'reason' => 'Excellent bilan annuel',
            'notes' => 'À confirmer par la direction',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.type', 'promotion')
            ->assertJsonPath('data.status', 'pending')
            // Snapshot de l'état courant au moment de la création.
            ->assertJsonPath('data.from_position_id', $position->id)
            ->assertJsonPath('data.from_department_id', $department->id)
            ->assertJsonPath('data.from_salary', 80000)
            ->assertJsonPath('data.to_salary', 100000)
            ->assertJsonPath('data.effective_date', '2026-09-01')
            ->assertJsonPath('data.reason', 'Excellent bilan annuel');

        $this->assertDatabaseHas('career_events', [
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'status' => 'pending',
            'from_salary' => 80000,
            'to_salary' => 100000,
        ]);
    }

    public function test_employee_cannot_create_career_event(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/career-events', [
            'employee_id' => $employee->id,
            'type' => 'raise',
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Ancienneté',
        ])->assertForbidden();
    }

    public function test_employee_sees_only_own_career_events(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();
        $other = $this->createEmployee($company, 'other@a.test', 'employee', null);

        CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'applied',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-08-01',
            'reason' => 'Ancienneté',
        ]);

        CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $other->id,
            'type' => 'promotion',
            'status' => 'pending',
            'effective_date' => '2026-09-01',
            'reason' => 'Promotion',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/career-events');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id)
            ->assertJsonPath('data.0.type', 'raise');
    }

    public function test_manager_can_approve_and_apply_promotion_updating_employee(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();
        $department = $this->createDepartment($company, 'Operations', $manager);
        $newDepartment = $this->createDepartment($company, 'Direction', $manager);
        $position = $this->createPosition($company, 'Chef', $department);
        $newPosition = $this->createPosition($company, 'Directeur', $newDepartment);

        $employee->forceFill([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'salary_base' => 80000,
        ])->save();

        $event = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'status' => 'pending',
            'from_position_id' => $position->id,
            'from_department_id' => $department->id,
            'from_salary' => 80000,
            'to_position_id' => $newPosition->id,
            'to_department_id' => $newDepartment->id,
            'to_salary' => 120000,
            'effective_date' => '2026-09-01',
            'reason' => 'Passage en direction',
        ]);

        Sanctum::actingAs($manager);

        $approve = $this->putJson("/api/v1/career-events/{$event->id}/approve");
        $approve->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $manager->id);
        $this->assertNotNull($approve->json('data.approved_at'));

        $apply = $this->putJson("/api/v1/career-events/{$event->id}/apply");
        $apply->assertOk()
            ->assertJsonPath('data.status', 'applied');
        $this->assertNotNull($apply->json('data.applied_at'));

        // Impact paie : l'employé porte désormais son nouveau poste et son
        // nouveau salaire de base (consommé par le prochain run de paie).
        $employee->refresh();
        $this->assertSame($newPosition->id, $employee->position_id);
        $this->assertSame($newDepartment->id, $employee->department_id);
        $this->assertSame(120000.0, (float) $employee->salary_base);
    }

    public function test_apply_requires_approved_status(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        $event = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'pending',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Ancienneté',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/career-events/{$event->id}/apply")->assertForbidden();
    }

    public function test_apply_without_target_returns_422(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        $event = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'title_change',
            'status' => 'approved',
            'effective_date' => '2026-09-01',
            'reason' => 'Intitulé seul',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/career-events/{$event->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CAREER_EVENT_NOTHING_TO_APPLY');
    }

    public function test_reject_marks_event_rejected_with_reason(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        $event = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'pending',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Ancienneté',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/career-events/{$event->id}/reject", ['reason' => 'Budget non validé'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $event->refresh();
        $this->assertStringContainsString('Budget non validé', (string) $event->notes);
        $this->assertDatabaseHas('career_events', ['id' => $event->id, 'status' => 'rejected']);
    }

    public function test_update_only_allowed_while_pending(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();
        $department = $this->createDepartment($company, 'Operations', $manager);
        $newDepartment = $this->createDepartment($company, 'Direction', $manager);

        $event = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'transfer',
            'status' => 'pending',
            'effective_date' => '2026-09-01',
            'reason' => 'Réorganisation',
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/career-events/{$event->id}", [
            'to_department_id' => $newDepartment->id,
            'reason' => 'Réorganisation — direction',
        ])->assertOk()
            ->assertJsonPath('data.to_department_id', $newDepartment->id)
            ->assertJsonPath('data.reason', 'Réorganisation — direction');

        $this->putJson("/api/v1/career-events/{$event->id}/approve")->assertOk();

        // Plus d'édition une fois sorti de pending.
        $this->putJson("/api/v1/career-events/{$event->id}", ['reason' => 'Tentative tardive'])
            ->assertForbidden();
    }

    public function test_delete_only_allowed_while_pending(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        $pending = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'pending',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Ancienneté',
        ]);

        $approved = CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'approved',
            'from_salary' => 80000,
            'to_salary' => 95000,
            'effective_date' => '2026-10-01',
            'reason' => 'Performance',
        ]);

        Sanctum::actingAs($manager);

        $this->deleteJson("/api/v1/career-events/{$pending->id}")->assertOk();
        $this->assertDatabaseMissing('career_events', ['id' => $pending->id]);

        $this->deleteJson("/api/v1/career-events/{$approved->id}")->assertForbidden();
    }

    public function test_cross_tenant_event_not_accessible(): void
    {
        [$companyA, $managerA] = $this->createCompanyActors();
        [, , $employeeB] = $this->createCompanyActors('Company B', 'company-b', 'manager.b@a.test', 'employee.b@a.test');

        $event = CareerEvent::query()->create([
            'company_id' => $employeeB->company_id,
            'employee_id' => $employeeB->id,
            'type' => 'raise',
            'status' => 'pending',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Ancienneté',
        ]);

        Sanctum::actingAs($managerA);

        // Le scope global BelongsToCompany → 404 (pas de fuite cross-tenant).
        $this->getJson("/api/v1/career-events/{$event->id}")->assertNotFound();
        $this->putJson("/api/v1/career-events/{$event->id}", ['reason' => 'Intrusion'])->assertNotFound();
        $this->deleteJson("/api/v1/career-events/{$event->id}")->assertNotFound();
    }

    public function test_store_rejects_cross_tenant_position_target(): void
    {
        [$companyA, $managerA] = $this->createCompanyActors();
        [, , $employeeB] = $this->createCompanyActors('Company B', 'company-b', 'manager.b@a.test', 'employee.b@a.test');
        $departmentB = $this->createDepartmentForCompany((string) $employeeB->company_id, 'Ops B', null);
        $positionB = $this->createPositionForCompany((string) $employeeB->company_id, 'Poste B', $departmentB->id);

        Sanctum::actingAs($managerA);

        // L'employé cible est scopé au tenant de l'acteur → 422.
        $this->postJson('/api/v1/career-events', [
            'employee_id' => $employeeB->id,
            'type' => 'promotion',
            'to_position_id' => $positionB->id,
            'effective_date' => '2026-09-01',
            'reason' => 'Test',
        ])->assertStatus(422);
    }

    public function test_department_scoped_manager_cannot_act_outside_department(): void
    {
        [$company, $manager] = $this->createCompanyActors();
        $ownDepartment = $this->createDepartment($company, 'Ma direction', $manager);
        $otherDepartment = $this->createDepartment($company, 'Autre service', null);

        $deptManager = $this->createEmployee($company, 'dept.manager@a.test', 'manager', 'dept');
        $deptManager->forceFill(['department_id' => $ownDepartment->id])->save();

        $outsideEmployee = $this->createEmployee($company, 'outside@a.test', 'employee', null);
        $outsideEmployee->forceFill(['department_id' => $otherDepartment->id])->save();

        Sanctum::actingAs($deptManager);

        // manager_role=dept restreint à son propre département (PA2-SEC-002).
        $this->postJson('/api/v1/career-events', [
            'employee_id' => $outsideEmployee->id,
            'type' => 'raise',
            'to_salary' => 90000,
            'effective_date' => '2026-09-01',
            'reason' => 'Hors périmètre',
        ])->assertForbidden();
    }

    public function test_mycareer_includes_career_events(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        CareerEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'raise',
            'status' => 'applied',
            'from_salary' => 80000,
            'to_salary' => 90000,
            'effective_date' => '2026-08-15',
            'reason' => 'Ancienneté',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/career');

        $response->assertOk()
            ->assertJsonCount(1, 'data.career_events')
            ->assertJsonPath('data.career_events.0.type', 'raise')
            ->assertJsonPath('data.career_events.0.status', 'applied')
            ->assertJsonPath('data.career_events.0.from_salary', 80000)
            ->assertJsonPath('data.career_events.0.to_salary', 90000);
    }

    public function test_store_requires_effective_date_and_reason(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/career-events', [
            'employee_id' => $employee->id,
            'type' => 'raise',
            'to_salary' => 90000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['effective_date', 'reason']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createCompanyActors(
        string $name = 'Company A',
        string $slug = 'company-a',
        string $managerEmail = 'manager@a.test',
        string $employeeEmail = 'employee@a.test',
    ): array {
        // Factory (pattern OrganigrammeTest) : remplit les champs NOT NULL du
        // vrai schéma (plan_id, subscription_start/end, language…) — un
        // Company::query()->create() manuel viole companies.plan_id NOT NULL
        // (SQLSTATE 23502, vu en CI le 2026-08-22).
        $company = Company::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $managerEmail,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, $managerEmail, 'manager', 'principal');
        $employee = $this->createEmployee($company, $employeeEmail, 'employee', null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role = 'employee',
        ?string $managerRole = null,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            // NOT NULL sans défaut sur le vrai schéma (leçon #4980) : sans
            // first_name/last_name → SQLSTATE 23502 (masqué par le fix plan_id).
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

    private function createDepartment(Company $company, string $name, ?Employee $manager): Department
    {
        return $this->createDepartmentForCompany($company->id, $name, $manager?->id);
    }

    private function createDepartmentForCompany(string $companyId, string $name, ?int $managerId): Department
    {
        $department = Department::create(['name' => $name, 'manager_id' => $managerId]);
        $department->company_id = $companyId;
        $department->save();

        return $department;
    }

    private function createPosition(Company $company, string $name, Department $department): Position
    {
        return $this->createPositionForCompany($company->id, $name, $department->id);
    }

    private function createPositionForCompany(string $companyId, string $name, int $departmentId): Position
    {
        $position = Position::create(['name' => $name, 'department_id' => $departmentId]);
        // forceFill : company_id est un UUID (string) — le docblock @property de
        // Position (int|null) est erroné mais baseliné ; forceFill évite le
        // finding PHPStan sur la nouvelle fixture.
        $position->forceFill(['company_id' => $companyId])->save();

        return $position;
    }
}

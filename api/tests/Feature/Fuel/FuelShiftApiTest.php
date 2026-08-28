<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API des shifts FuelStation — FUEL-005 (issue #5799).
 *
 * Couvre : auth 401, RBAC (employé 403), CRUD shifts tenant-scoped,
 * unicité du nom par tenant, affectations avec contrôle de chevauchement
 * (SHIFT_OVERLAP), rejet d'un employé hors tenant (EMPLOYEE_OUTSIDE_TENANT),
 * self-service pompiste (/fuel-station/me/shifts), suppression refusée d'un shift
 * affecté, annulation d'affectation.
 */
class FuelShiftApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/shifts')->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/me/shifts')->assertStatus(401);
    }

    public function test_operator_employee_cannot_manage_shifts(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/shifts')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/shifts', [
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ])->assertStatus(403);
    }

    public function test_manager_creates_and_lists_shift(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/shifts', [
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'notes' => 'Équipe A',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Matin')
            ->assertJsonPath('data.start_time', '06:00')
            ->assertJsonPath('data.end_time', '14:00')
            ->assertJsonPath('data.company_id', $company->id);

        $this->getJson('/api/v1/fuel-station/shifts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Matin');
    }

    public function test_shift_name_must_be_unique_per_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/shifts', ['name' => 'Matin', 'start_time' => '06:00', 'end_time' => '14:00'])
            ->assertStatus(201);

        $this->postJson('/api/v1/fuel-station/shifts', ['name' => 'Matin', 'start_time' => '14:00', 'end_time' => '22:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_same_shift_name_allowed_in_another_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);
        $this->postJson('/api/v1/fuel-station/shifts', ['name' => 'Matin', 'start_time' => '06:00', 'end_time' => '14:00'])
            ->assertStatus(201);

        Sanctum::actingAs($managerB);
        $this->postJson('/api/v1/fuel-station/shifts', ['name' => 'Matin', 'start_time' => '06:00', 'end_time' => '14:00'])
            ->assertStatus(201);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/shifts', ['name' => 'Nuit', 'start_time' => '22:00', 'end_time' => '06:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_time');
    }

    public function test_cross_tenant_shift_access_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        $shift = FuelShift::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}")->assertStatus(404);
        $this->putJson("/api/v1/fuel-station/shifts/{$shift->id}", ['name' => 'X'])->assertStatus(404);
        $this->deleteJson("/api/v1/fuel-station/shifts/{$shift->id}")->assertStatus(404);
    }

    public function test_manager_assigns_operator_to_shift(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.employee_id', $operator->id)
            ->assertJsonPath('data.assignment_date', '2026-09-01')
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_overlapping_assignment_same_day_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $morning = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);
        $afternoon = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Après-midi',
            'start_time' => '13:00',
            'end_time' => '21:00',
        ]);
        $night = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Nuit',
            'start_time' => '21:00',
            'end_time' => '06:00',
        ]);
        // Shift inactif : l'affectation dessus est refusée (SHIFT_INACTIVE).
        $inactive = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Ancien',
            'start_time' => '04:00',
            'end_time' => '05:00',
            'status' => 'inactive',
        ]);

        $this->postJson("/api/v1/fuel-station/shifts/{$morning->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])->assertStatus(201);

        // Chevauchement (13:00 < 14:00) → 422 SHIFT_OVERLAP.
        $this->postJson("/api/v1/fuel-station/shifts/{$afternoon->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SHIFT_OVERLAP');

        // Shift inactif → 422 SHIFT_INACTIVE (même sans chevauchement).
        $this->postJson("/api/v1/fuel-station/shifts/{$inactive->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SHIFT_INACTIVE');
    }

    public function test_assigning_employee_from_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($managerA);

        $shift = FuelShift::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $foreignEmployee->id,
            'assignment_date' => '2026-09-01',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'EMPLOYEE_OUTSIDE_TENANT');
    }

    public function test_operator_sees_only_own_assignments_via_me_shifts(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $operatorA->id,
            'assignment_date' => '2026-09-01',
        ])->assertStatus(201);
        $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $operatorB->id,
            'assignment_date' => '2026-09-02',
        ])->assertStatus(201);

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/fuel-station/me/shifts?date_from=2026-09-01&date_to=2026-09-30')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $operatorA->id)
            ->assertJsonPath('data.0.shift.name', 'Matin');
    }

    public function test_delete_shift_with_assignments_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])->assertStatus(201);

        $this->deleteJson("/api/v1/fuel-station/shifts/{$shift->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'SHIFT_HAS_ASSIGNMENTS');

        // Après annulation des affectations, la suppression passe.
        $assignment = FuelShiftAssignment::query()
            ->where('shift_id', $shift->id)
            ->firstOrFail();
        $this->deleteJson("/api/v1/fuel-station/shift-assignments/{$assignment->id}")->assertStatus(200);

        $this->deleteJson("/api/v1/fuel-station/shifts/{$shift->id}")->assertStatus(200);
    }

    public function test_manager_cancels_assignment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        /** @var int $assignmentId */
        $assignmentId = $this->postJson("/api/v1/fuel-station/shifts/{$shift->id}/assignments", [
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ])->assertStatus(201)->json('data.id');

        $this->deleteJson("/api/v1/fuel-station/shift-assignments/{$assignmentId}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('fuel_shift_assignments', [
            'id' => $assignmentId,
            'status' => FuelShiftAssignment::STATUS_CANCELLED,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranchStaff;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7909 — Affectation d'employés aux succursales restaurant.
 *
 * Couvre le CRUD /restaurant/branches/{branch}/staff (liste paginée avec
 * employé, affectation, mise à jour du rôle, retrait en soft delete),
 * l'unicité (409 sur doublon actif, restauration après retrait), le RBAC
 * (employé simple → 403) et l'isolation cross-tenant (employé d'un autre
 * tenant → 422, branche d'un autre tenant → 404).
 */
class RestaurantBranchStaffTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function selfService(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_staff_assignment_crud(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        app(TenantManager::class)->withinTenant($company, function () use ($staff): void {
            $branch = RestaurantBranch::factory()->create();

            // Affectation → 201, employé (nom) embarqué.
            $created = $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", [
                'employee_id' => $staff->id,
                'role' => 'serveur',
            ])->assertStatus(201)
                ->assertJsonFragment(['employee_id' => $staff->id, 'role' => 'serveur'])
                ->assertJsonPath('data.employee.id', $staff->id);

            $assignmentId = $created->json('data.id');
            self::assertIsInt($assignmentId);

            // Liste paginée : l'affectation apparaît avec l'employé.
            $this->getJson("/api/v1/restaurant/branches/{$branch->id}/staff")
                ->assertOk()
                ->assertJsonPath('data.0.id', $assignmentId)
                ->assertJsonPath('data.0.employee.first_name', $staff->first_name)
                ->assertJsonStructure(['data', 'links', 'meta']);

            // Mise à jour du rôle.
            $this->patchJson("/api/v1/restaurant/branches/{$branch->id}/staff/{$assignmentId}", [
                'role' => 'chef de rang',
            ])->assertOk()
                ->assertJsonFragment(['role' => 'chef de rang']);

            // Retrait → soft delete (ligne conservée en base).
            $this->deleteJson("/api/v1/restaurant/branches/{$branch->id}/staff/{$assignmentId}")
                ->assertStatus(204);

            $this->getJson("/api/v1/restaurant/branches/{$branch->id}/staff")
                ->assertOk()
                ->assertJsonCount(0, 'data');

            self::assertTrue(
                RestaurantBranchStaff::withTrashed()->whereKey($assignmentId)->firstOrFail()->trashed()
            );
        });
    }

    public function test_duplicate_active_assignment_is_rejected_with_409(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        app(TenantManager::class)->withinTenant($company, function () use ($staff): void {
            $branch = RestaurantBranch::factory()->create();
            $payload = ['employee_id' => $staff->id, 'role' => 'serveur'];

            $created = $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", $payload)
                ->assertStatus(201);

            // Doublon actif → 409.
            $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", $payload)
                ->assertStatus(409);

            // Après retrait, la ré-affectation restaure la ligne (même id).
            $assignmentId = $created->json('data.id');
            $this->deleteJson("/api/v1/restaurant/branches/{$branch->id}/staff/{$assignmentId}")
                ->assertStatus(204);

            $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", [
                'employee_id' => $staff->id,
                'role' => 'cuisinier',
            ])->assertStatus(201)
                ->assertJsonPath('data.id', $assignmentId)
                ->assertJsonFragment(['role' => 'cuisinier']);
        });
    }

    public function test_employee_from_another_tenant_is_rejected_with_422(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $other->id]);

        app(TenantManager::class)->withinTenant($company, function () use ($foreignEmployee): void {
            $branch = RestaurantBranch::factory()->create();

            $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", [
                'employee_id' => $foreignEmployee->id,
                'role' => 'serveur',
            ])->assertStatus(422);

            self::assertSame(0, RestaurantBranchStaff::query()->count());
        });
    }

    public function test_branch_from_another_tenant_returns_404(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $other->setFeature('restaurantmanager', true);
        $other->save();

        $foreignBranchId = app(TenantManager::class)->withinTenant(
            $other,
            fn (): int => RestaurantBranch::factory()->create()->id
        );

        app(TenantManager::class)->withinTenant($company, function () use ($foreignBranchId, $staff): void {
            $this->getJson("/api/v1/restaurant/branches/{$foreignBranchId}/staff")
                ->assertStatus(404);

            $this->postJson("/api/v1/restaurant/branches/{$foreignBranchId}/staff", [
                'employee_id' => $staff->id,
            ])->assertStatus(404);
        });
    }

    public function test_plain_employee_cannot_manage_staff(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $principal, $staff): void {
            $branch = RestaurantBranch::factory()->create();

            $created = $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", [
                'employee_id' => $staff->id,
                'role' => 'serveur',
            ])->assertStatus(201);

            $assignmentId = $created->json('data.id');

            // Employé simple : lecture OK, écriture 403.
            $this->selfService($company);

            $this->getJson("/api/v1/restaurant/branches/{$branch->id}/staff")
                ->assertOk();

            $this->postJson("/api/v1/restaurant/branches/{$branch->id}/staff", [
                'employee_id' => $principal->id,
            ])->assertStatus(403);

            $this->patchJson("/api/v1/restaurant/branches/{$branch->id}/staff/{$assignmentId}", [
                'role' => 'plongeur',
            ])->assertStatus(403);

            $this->deleteJson("/api/v1/restaurant/branches/{$branch->id}/staff/{$assignmentId}")
                ->assertStatus(403);
        });
    }
}

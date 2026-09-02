<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-301 (#6182) — CRUD du référentiel branches/zones/tables.
 *
 * Couvre le CRUD complet, le RBAC (principal/rh requis en écriture sur les
 * branches ; le manager de salle gère le plan de salle) et l'isolation
 * cross-tenant : une branche ou une zone d'un autre tenant renvoie 404,
 * jamais 403, et une zone d'un autre tenant est rejetée en 422 à la
 * création d'une table (règle `exists` tenant-scopée).
 */
class RestaurantReferentialCrudTest extends TestCase
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

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_principal_can_create_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/branches', [
            'code' => 'BR-001',
            'name' => 'Branche Centrale',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'BR-001']);
    }

    public function test_ordinary_employee_cannot_create_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);

        $this->postJson('/api/v1/restaurant/branches', [
            'code' => 'BR-002',
            'name' => 'Branche Secondaire',
        ])->assertStatus(403);
    }

    public function test_index_lists_branches(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantBranch::factory()->create(['code' => 'BR-003', 'name' => 'Branche Liste']);
        });

        $this->getJson('/api/v1/restaurant/branches')
            ->assertOk()
            ->assertJsonFragment(['code' => 'BR-003']);
    }

    public function test_show_zone_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $zoneId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantZone::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/zones/{$zoneId}")->assertStatus(404);
    }

    public function test_create_table_with_zone_of_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $foreignZoneId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantZone::factory()->create()->id;
        });

        $branchId = app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): int {
            return RestaurantBranch::factory()->create(['company_id' => $companyA->id])->id;
        });

        $this->principal($companyA);

        $this->postJson('/api/v1/restaurant/tables', [
            'label' => 'Table 12',
            'branch_id' => $branchId,
            'zone_id' => $foreignZoneId,
        ])->assertStatus(422);
    }

    public function test_update_and_delete_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/restaurant/branches/{$branchId}", ['name' => 'Branche Renovee'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Branche Renovee']);

        $this->deleteJson("/api/v1/restaurant/branches/{$branchId}")->assertStatus(204);
    }

    public function test_manager_role_can_create_zone_and_table(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->postJson('/api/v1/restaurant/zones', [
            'name' => 'Zone Terrasse',
            'branch_id' => $branchId,
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Zone Terrasse']);

        $this->postJson('/api/v1/restaurant/tables', [
            'label' => 'Table 12',
            'branch_id' => $branchId,
            'capacity' => 4,
        ])->assertStatus(201)
            ->assertJsonFragment(['label' => 'Table 12']);
    }
}

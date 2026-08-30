<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-605 (#6210) — CRUD des livreurs RestaurantManager.
 *
 * Couvre le CRUD complet, le RBAC (principal/rh/manager en écriture) et
 * l'isolation cross-tenant (404, jamais 403).
 */
class RestaurantDeliveryRiderCrudTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
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

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_principal_can_create_rider(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        $this->postJson('/api/v1/restaurant/delivery-riders', [
            'branch_id' => $branch->id,
            'name' => 'Jean Livreur',
            'phone' => '+237600000000',
            'vehicle_code' => 'VH-01',
            'is_active' => true,
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Jean Livreur', 'is_active' => true]);
    }

    public function test_ordinary_employee_cannot_create_rider(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);

        $this->postJson('/api/v1/restaurant/delivery-riders', [
            'branch_id' => 1,
            'name' => 'Nope',
        ])->assertStatus(403);
    }

    public function test_manager_can_update_and_delete_rider(): void
    {
        $company = $this->company();
        $this->manager($company, 'manager');

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        /** @var RestaurantDeliveryRider $rider */
        $rider = RestaurantDeliveryRider::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->putJson("/api/v1/restaurant/delivery-riders/{$rider->id}", [
            'is_active' => false,
        ])->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);

        $this->deleteJson("/api/v1/restaurant/delivery-riders/{$rider->id}")->assertStatus(204);
    }

    public function test_rider_of_other_tenant_returns_404(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var RestaurantBranch $otherBranch */
        $otherBranch = RestaurantBranch::factory()->create(['company_id' => $otherCompany->id]);

        /** @var RestaurantDeliveryRider $rider */
        $rider = RestaurantDeliveryRider::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
        ]);

        $this->getJson("/api/v1/restaurant/delivery-riders/{$rider->id}")->assertStatus(404);
    }

    public function test_create_rider_requires_branch_of_tenant(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var RestaurantBranch $otherBranch */
        $otherBranch = RestaurantBranch::factory()->create(['company_id' => $otherCompany->id]);

        $this->postJson('/api/v1/restaurant/delivery-riders', [
            'branch_id' => $otherBranch->id,
            'name' => 'Cross tenant',
        ])->assertStatus(422);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-304 (#6185) / RESTO-305 (#6186) — CRUD du référentiel
 * menus/items/horaires + fournisseurs.
 *
 * Couvre le CRUD complet, le RBAC (principal/rh requis sur les fournisseurs ;
 * le manager de salle gère menus, items et horaires) et l'isolation
 * cross-tenant : une ressource d'un autre tenant renvoie 404, jamais 403,
 * et un produit d'un autre tenant est rejeté en 422 (règle `exists`
 * tenant-scopée).
 */
class RestaurantMenuSupplierCrudTest extends TestCase
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

    private function floorManager(Company $company): Employee
    {
        // `manager_role = 'manager'` n'est pas encore stockable en base
        // (CHECK `employees_manager_role_check` limité à principal/rh/dept/
        // comptable/superviseur/marketing — cf. RestaurantRbacMatrixTest) :
        // l'acteur est construit en mémoire (forceFill, aucune requête),
        // le middleware tenant résout la compagnie via la relation `company`.
        /** @var Employee $employee */
        $employee = new Employee;
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
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

    public function test_principal_can_create_menu(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/menus', [
            'code' => 'MEN-001',
            'name' => 'Menu du Jour',
            'price_minor' => 1200,
            'currency' => 'DZD',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'MEN-001'])
            ->assertJsonFragment(['name' => 'Menu du Jour'])
            ->assertJsonFragment(['price_minor' => 1200]);
    }

    public function test_menu_code_is_unique_per_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            RestaurantMenu::factory()->create(['company_id' => $company->id, 'code' => 'MEN-001']);
        });

        $this->principal($company);

        $this->postJson('/api/v1/restaurant/menus', [
            'code' => 'MEN-001',
            'name' => 'Menu Doublon',
            'price_minor' => 800,
        ])->assertStatus(422);
    }

    public function test_menu_code_is_reusable_across_tenants(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyB);

        app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): void {
            RestaurantMenu::factory()->create(['company_id' => $companyA->id, 'code' => 'MEN-001']);
        });

        $this->principal($companyB);

        $this->postJson('/api/v1/restaurant/menus', [
            'code' => 'MEN-001',
            'name' => 'Menu Autre Tenant',
            'price_minor' => 900,
        ])->assertStatus(201);
    }

    public function test_update_and_delete_menu(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $menuId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantMenu::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/restaurant/menus/{$menuId}", ['name' => 'Menu Renove'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Menu Renove']);

        $this->deleteJson("/api/v1/restaurant/menus/{$menuId}")->assertStatus(204);
    }

    public function test_show_menu_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $menuId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantMenu::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/menus/{$menuId}")->assertStatus(404);
    }

    public function test_floor_manager_can_create_menu_item_in_own_menu(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->floorManager($company);

        $ids = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            return [
                'menu' => RestaurantMenu::factory()->create(['company_id' => $company->id])->id,
                'product' => RestaurantProduct::factory()->create(['company_id' => $company->id])->id,
            ];
        });

        /** @var array{menu: int, product: int} $ids */
        $this->postJson("/api/v1/restaurant/menus/{$ids['menu']}/items", [
            'product_id' => $ids['product'],
            'position' => 1,
            'is_optional' => false,
        ])->assertStatus(201)
            ->assertJsonFragment(['product_id' => $ids['product']]);
    }

    public function test_item_in_menu_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $foreignMenuId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantMenu::factory()->create()->id;
        });

        $productId = app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): int {
            return RestaurantProduct::factory()->create(['company_id' => $companyA->id])->id;
        });

        $this->principal($companyA);

        $this->postJson("/api/v1/restaurant/menus/{$foreignMenuId}/items", [
            'product_id' => $productId,
        ])->assertStatus(404);
    }

    public function test_item_with_product_of_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $menuId = app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): int {
            return RestaurantMenu::factory()->create(['company_id' => $companyA->id])->id;
        });

        $foreignProductId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantProduct::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->postJson("/api/v1/restaurant/menus/{$menuId}/items", [
            'product_id' => $foreignProductId,
        ])->assertStatus(422);
    }

    public function test_update_and_delete_menu_item(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $itemId = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $menu = RestaurantMenu::factory()->create(['company_id' => $company->id]);
            $product = RestaurantProduct::factory()->create(['company_id' => $company->id]);

            return [
                'menu' => $menu->id,
                'item' => $menu->items()->create([
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                    'position' => 0,
                    'is_optional' => false,
                ])->id,
            ];
        });

        /** @var array{menu: int, item: int} $itemId */
        $this->putJson("/api/v1/restaurant/menus/{$itemId['menu']}/items/{$itemId['item']}", [
            'position' => 3,
        ])->assertOk()
            ->assertJsonFragment(['position' => 3]);

        $this->deleteJson("/api/v1/restaurant/menus/{$itemId['menu']}/items/{$itemId['item']}")->assertStatus(204);
    }

    public function test_floor_manager_can_create_hour(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->floorManager($company);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->postJson('/api/v1/restaurant/hours', [
            'branch_id' => $branchId,
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '22:00',
        ])->assertStatus(201)
            ->assertJsonFragment(['day_of_week' => 1]);
    }

    public function test_closed_day_hour_without_times_is_accepted(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->postJson('/api/v1/restaurant/hours', [
            'branch_id' => $branchId,
            'day_of_week' => 6,
            'is_closed' => true,
        ])->assertStatus(201)
            ->assertJsonFragment(['is_closed' => true]);
    }

    public function test_hour_closing_before_opening_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->postJson('/api/v1/restaurant/hours', [
            'branch_id' => $branchId,
            'day_of_week' => 2,
            'opens_at' => '22:00',
            'closes_at' => '09:00',
        ])->assertStatus(422);
    }

    public function test_open_day_hour_without_times_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $branchId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantBranch::factory()->create(['company_id' => $company->id])->id;
        });

        $this->postJson('/api/v1/restaurant/hours', [
            'branch_id' => $branchId,
            'day_of_week' => 3,
        ])->assertStatus(422);
    }

    public function test_update_and_delete_hour(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $hourId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantHour::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/restaurant/hours/{$hourId}", ['opens_at' => '08:00'])
            ->assertOk()
            ->assertJsonFragment(['opens_at' => '08:00']);

        $this->deleteJson("/api/v1/restaurant/hours/{$hourId}")->assertStatus(204);
    }

    public function test_show_hour_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $hourId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantHour::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/hours/{$hourId}")->assertStatus(404);
    }

    public function test_principal_can_create_supplier(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/suppliers', [
            'name' => 'Fournisseur Fruits',
            'contact_phone' => '+2135550001',
            'email' => 'contact@fruits.example',
            'address' => '12 Rue des Vergers',
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Fournisseur Fruits']);
    }

    public function test_update_and_delete_supplier(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $supplierId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return RestaurantSupplier::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/restaurant/suppliers/{$supplierId}", ['name' => 'Fournisseur Renove'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Fournisseur Renove']);

        $this->deleteJson("/api/v1/restaurant/suppliers/{$supplierId}")->assertStatus(204);
    }

    public function test_show_supplier_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $supplierId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantSupplier::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/suppliers/{$supplierId}")->assertStatus(404);
    }

    public function test_floor_manager_cannot_create_supplier(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->floorManager($company);

        $this->postJson('/api/v1/restaurant/suppliers', [
            'name' => 'Fournisseur Refuse',
        ])->assertStatus(403);
    }

    public function test_ordinary_employee_cannot_write_referential(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);

        $this->postJson('/api/v1/restaurant/menus', [
            'code' => 'MEN-FORBIDDEN',
            'name' => 'Menu Interdit',
            'price_minor' => 100,
        ])->assertStatus(403);

        $branchId = app(TenantManager::class)->withinTenant($company, function (): int {
            return RestaurantBranch::factory()->create(['code' => 'BR-FORBIDDEN'])->id;
        });

        $this->postJson('/api/v1/restaurant/hours', [
            'branch_id' => $branchId,
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '22:00',
        ])->assertStatus(403);

        $this->postJson('/api/v1/restaurant/suppliers', [
            'name' => 'Fournisseur Interdit',
        ])->assertStatus(403);
    }

    public function test_ordinary_employee_can_read_referential(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            RestaurantMenu::factory()->create(['company_id' => $company->id, 'code' => 'MEN-READ']);
        });

        $this->ordinaryEmployee($company);

        $this->getJson('/api/v1/restaurant/menus')
            ->assertOk()
            ->assertJsonFragment(['code' => 'MEN-READ']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/restaurant/menus')->assertStatus(401);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-302/303 (#6183/#6184) — CRUD du référentiel restaurant (catalogue).
 *
 * Couvre le CRUD catégories/produits/recettes + ingrédients/unités/taxes,
 * le RBAC (manager principal ou RH requis en écriture) et l'isolation
 * cross-tenant : une ressource d'un autre tenant renvoie systématiquement
 * 404, jamais 403.
 */
class RestaurantCatalogueCrudTest extends TestCase
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

    public function test_principal_can_create_product_with_category(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $categoryId = app(TenantManager::class)->withinTenant($company, function (): int {
            return RestaurantCategory::factory()->create()->id;
        });

        $this->postJson('/api/v1/restaurant/products', [
            'code' => 'PRD-001',
            'name' => 'Couscous Royal',
            'category_id' => $categoryId,
            'price_minor' => 1500,
            'currency' => 'DZD',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'PRD-001'])
            ->assertJsonFragment(['name' => 'Couscous Royal']);
    }

    public function test_ordinary_employee_cannot_create_product(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);

        $categoryId = app(TenantManager::class)->withinTenant($company, function (): int {
            return RestaurantCategory::factory()->create()->id;
        });

        $this->postJson('/api/v1/restaurant/products', [
            'code' => 'PRD-002',
            'name' => 'Salade Ceasar',
            'category_id' => $categoryId,
            'price_minor' => 900,
        ])->assertStatus(403);
    }

    public function test_show_product_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $productId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantProduct::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/products/{$productId}")->assertStatus(404);
    }

    public function test_ingredient_link_with_foreign_ingredient_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $productId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return RestaurantProduct::factory()->create()->id;
        });

        $foreignIngredientId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantIngredient::factory()->create()->id;
        });

        $this->postJson("/api/v1/restaurant/products/{$productId}/ingredients", [
            'ingredient_id' => $foreignIngredientId,
            'quantity' => 0.5,
            'unit_code' => 'kg',
        ])->assertStatus(422);
    }

    public function test_principal_can_add_list_and_remove_ingredient_link(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        [$productId, $ingredientId] = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                RestaurantProduct::factory()->create()->id,
                RestaurantIngredient::factory()->create()->id,
            ];
        });

        $this->postJson("/api/v1/restaurant/products/{$productId}/ingredients", [
            'ingredient_id' => $ingredientId,
            'quantity' => 0.5,
            'unit_code' => 'kg',
        ])->assertStatus(201)
            ->assertJsonFragment(['ingredient_id' => $ingredientId]);

        $this->getJson("/api/v1/restaurant/products/{$productId}/ingredients")
            ->assertOk()
            ->assertJsonFragment(['unit_code' => 'kg']);

        $linkId = app(TenantManager::class)->withinTenant($company, function () use ($productId, $ingredientId): int {
            return RestaurantProductIngredient::query()
                ->where('product_id', $productId)
                ->where('ingredient_id', $ingredientId)
                ->firstOrFail()->id;
        });

        $this->deleteJson("/api/v1/restaurant/products/{$productId}/ingredients/{$linkId}")
            ->assertStatus(204);

        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function () use ($productId): int {
            return RestaurantProductIngredient::query()->where('product_id', $productId)->count();
        }));
    }

    public function test_principal_can_crud_units_and_tax_rates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        // Unité : create / update / delete.
        $this->postJson('/api/v1/restaurant/units', [
            'code' => 'kg',
            'label' => 'Kilogramme',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'kg']);

        $unitId = app(TenantManager::class)->withinTenant($company, function (): int {
            return RestaurantUnit::query()->where('code', 'kg')->firstOrFail()->id;
        });

        $this->putJson("/api/v1/restaurant/units/{$unitId}", ['label' => 'Kilogramme'])
            ->assertOk()
            ->assertJsonFragment(['label' => 'Kilogramme']);

        $this->deleteJson("/api/v1/restaurant/units/{$unitId}")->assertStatus(204);

        // Taux de TVA : create / update / delete.
        $this->postJson('/api/v1/restaurant/tax-rates', [
            'code' => 'TVA19',
            'label' => 'TVA 19%',
            'rate_bps' => 1900,
            'is_default' => false,
        ])->assertStatus(201)
            ->assertJsonFragment(['rate_bps' => 1900]);

        $taxRateId = app(TenantManager::class)->withinTenant($company, function (): int {
            return RestaurantTaxRate::query()->where('code', 'TVA19')->firstOrFail()->id;
        });

        $this->putJson("/api/v1/restaurant/tax-rates/{$taxRateId}", ['label' => 'TVA 19%'])
            ->assertOk()
            ->assertJsonFragment(['label' => 'TVA 19%']);

        $this->deleteJson("/api/v1/restaurant/tax-rates/{$taxRateId}")->assertStatus(204);
    }

    public function test_index_lists_catalogue_for_authenticated_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantCategory::factory()->create(['name' => 'Plats']);
            RestaurantProduct::factory()->create(['code' => 'PRD-010']);
            RestaurantIngredient::factory()->create(['code' => 'ING-010']);
            RestaurantUnit::factory()->create(['code' => 'pce']);
            RestaurantTaxRate::factory()->create(['code' => 'TVA0']);
        });

        $this->getJson('/api/v1/restaurant/categories')->assertOk()->assertJsonFragment(['name' => 'Plats']);
        $this->getJson('/api/v1/restaurant/products')->assertOk()->assertJsonFragment(['code' => 'PRD-010']);
        $this->getJson('/api/v1/restaurant/ingredients')->assertOk()->assertJsonFragment(['code' => 'ING-010']);
        $this->getJson('/api/v1/restaurant/units')->assertOk()->assertJsonFragment(['code' => 'pce']);
        $this->getJson('/api/v1/restaurant/tax-rates')->assertOk()->assertJsonFragment(['code' => 'TVA0']);
    }
}

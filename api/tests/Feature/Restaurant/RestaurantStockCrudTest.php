<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-501 (#6200) — Niveaux de stock & mouvements (raisons, références).
 *
 * Couvre le CRUD des niveaux de stock, le journal des mouvements (raisons
 * bornées par l'enum, référence polymorphe), l'invariant « jamais de stock
 * négatif » (422) et l'isolation cross-tenant (404).
 */
class RestaurantStockCrudTest extends TestCase
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

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * Crée branche + ingrédient dans le tenant courant.
     *
     * @return array{branch: RestaurantBranch, ingredient: RestaurantIngredient}
     */
    private function branchAndIngredient(): array
    {
        $branch = RestaurantBranch::factory()->create();
        $ingredient = RestaurantIngredient::factory()->create(['branch_id' => $branch->id]);

        return ['branch' => $branch, 'ingredient' => $ingredient];
    }

    public function test_principal_can_create_stock_level(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        [$branch, $ingredient] = app(TenantManager::class)->withinTenant($company, fn () => $this->branchAndIngredient());

        $this->postJson('/api/v1/restaurant/stock-levels', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 25,
            'alert_threshold' => 5,
        ])->assertStatus(201)
            ->assertJsonFragment(['ingredient_id' => $ingredient->id, 'quantity' => '25.000']);
    }

    public function test_duplicate_stock_level_is_rejected_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        [$branch, $ingredient] = app(TenantManager::class)->withinTenant($company, fn () => $this->branchAndIngredient());

        $payload = ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10];

        $this->postJson('/api/v1/restaurant/stock-levels', $payload)->assertStatus(201);
        $this->postJson('/api/v1/restaurant/stock-levels', $payload)->assertStatus(422);
    }

    public function test_server_cannot_create_stock_level(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        [$branch, $ingredient] = app(TenantManager::class)->withinTenant($company, fn () => $this->branchAndIngredient());

        $this->postJson('/api/v1/restaurant/stock-levels', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
        ])->assertStatus(403);
    }

    public function test_show_stock_level_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $levelId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return RestaurantStockLevel::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/restaurant/stock-levels/{$levelId}")->assertStatus(404);
    }

    public function test_principal_can_apply_movement_and_stock_is_updated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $result = app(TenantManager::class)->withinTenant($company, function () {
            [$branch, $ingredient] = $this->branchAndIngredient();
            $level = RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 10,
            ]);

            $this->postJson('/api/v1/restaurant/inventory-movements', [
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity_delta' => 5,
                'reason_code' => StockMovementReason::RECEIVING->value,
                'note_redacted' => 'Livraison matinale',
            ])->assertStatus(201)
                ->assertJsonFragment(['reason_code' => StockMovementReason::RECEIVING->value, 'quantity_delta' => '5.000']);

            return ['level' => $level, 'branch' => $branch, 'ingredient' => $ingredient];
        });

        $this->assertSame('15.000', (string) $result['level']->refresh()->quantity);
        $this->assertSame(1, RestaurantInventoryMovement::query()
            ->where('company_id', $company->id)
            ->where('ingredient_id', $result['ingredient']->id)
            ->count());
    }

    public function test_movement_below_zero_is_rejected_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            [$branch, $ingredient] = $this->branchAndIngredient();

            $this->postJson('/api/v1/restaurant/inventory-movements', [
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity_delta' => -3,
                'reason_code' => StockMovementReason::WASTE->value,
            ])->assertStatus(422);
        });
    }

    public function test_invalid_reason_code_is_rejected_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            [$branch, $ingredient] = $this->branchAndIngredient();

            $this->postJson('/api/v1/restaurant/inventory-movements', [
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity_delta' => 1,
                'reason_code' => 'voodoo',
            ])->assertStatus(422);
        });
    }

    public function test_movement_index_filters_by_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            [$branch, $ingredient] = $this->branchAndIngredient();

            $this->postJson('/api/v1/restaurant/inventory-movements', [
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity_delta' => 2,
                'reason_code' => StockMovementReason::RECEIVING->value,
            ])->assertStatus(201);

            $this->getJson('/api/v1/restaurant/inventory-movements?reason_code='.StockMovementReason::RECEIVING->value)
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    }
}

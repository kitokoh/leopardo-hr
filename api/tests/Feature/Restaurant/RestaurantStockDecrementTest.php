<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-411 (#6198) — Décrément de stock à la confirmation de commande.
 *
 * Invariant stock (spec §4.4 / D4) : la confirmation consomme les ingrédients
 * de la recette en transaction (SELECT FOR UPDATE) ; deux commandes
 * simultanées sur le dernier stock → une seule passe, l'autre est refusée
 * (422) ; le stock n'est JAMAIS négatif ; chaque consommation est tracée dans
 * `restaurant_inventory_movements` (reason_code sale, référence commande).
 */
class RestaurantStockDecrementTest extends TestCase
{
    use RefreshTenantDatabase;

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
     * Crée une commande draft + produit dont la recette consomme
     * `requiredQuantity` du stock de l'ingrédient.
     *
     * @return array{branch: RestaurantBranch, product: RestaurantProduct, ingredient: RestaurantIngredient, order: RestaurantOrder}
     */
    private function makeOrderWithRecipe(
        Company $company,
        float $ingredientStock,
        float $requiredQuantity,
    ): array {
        return app(TenantManager::class)->withinTenant($company, function () use ($ingredientStock, $requiredQuantity): array {
            $branch = RestaurantBranch::factory()->create();
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'is_available' => true,
            ]);
            $ingredient = RestaurantIngredient::factory()->create([
                'branch_id' => $branch->id,
            ]);
            RestaurantProductIngredient::factory()->create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $requiredQuantity,
            ]);
            RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $ingredientStock,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);

            return ['branch' => $branch, 'product' => $product, 'ingredient' => $ingredient, 'order' => $order];
        });
    }

    private function addItemAndSubmit(RestaurantOrder $order, int $productId): void
    {
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $productId, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);
    }

    public function test_confirm_decrements_stock_and_traces_movement(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product, 'ingredient' => $ingredient, 'branch' => $branch] = $this->makeOrderWithRecipe($company, 5.0, 2.0);

        $this->addItemAndSubmit($order, (int) $product->id);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation');

        $stock = app(TenantManager::class)->withinTenant($company, fn (): float => (float) RestaurantStockLevel::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $this->assertSame(3.0, $stock);

        $movement = app(TenantManager::class)->withinTenant($company, fn () => RestaurantInventoryMovement::query()
            ->where('company_id', $company->id)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->first());

        $this->assertNotNull($movement);
        $this->assertSame('sale', $movement->reason_code->value);
        $this->assertSame(-2.0, (float) $movement->quantity_delta);
    }

    public function test_two_orders_on_last_stock_only_first_passes_and_stock_never_negative(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        // Une seule branche, un seul ingrédient, stock unique de 1.0 : les deux
        // commandes consomment LE MÊME stock (dernier exemplaire).
        $fixture = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'is_available' => true,
            ]);
            $ingredient = RestaurantIngredient::factory()->create(['branch_id' => $branch->id]);
            RestaurantProductIngredient::factory()->create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 1.0,
            ]);
            $stock = RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 1.0,
            ]);
            $orderA = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);
            $orderB = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);

            return [
                'branch' => $branch,
                'product' => $product,
                'ingredient' => $ingredient,
                'stock' => $stock,
                'orderA' => $orderA,
                'orderB' => $orderB,
            ];
        });

        $productId = (int) $fixture['product']->id;
        $this->addItemAndSubmit($fixture['orderA'], $productId);
        $this->addItemAndSubmit($fixture['orderB'], $productId);

        // Première confirmation : consomme le dernier stock.
        $this->postJson("/api/v1/restaurant/orders/{$fixture['orderA']->id}/confirm")->assertStatus(200);

        // Seconde confirmation sur le même ingrédient : stock insuffisant → 422.
        $this->postJson("/api/v1/restaurant/orders/{$fixture['orderB']->id}/confirm")->assertStatus(422);

        // La commande B reste `open` (transition annulée par le rollback).
        $this->assertSame('open', $fixture['orderB']->refresh()->status->value);

        $stock = app(TenantManager::class)->withinTenant($company, fn (): float => (float) RestaurantStockLevel::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $fixture['branch']->id)
            ->where('ingredient_id', $fixture['ingredient']->id)
            ->value('quantity'));

        // Stock jamais négatif : plancher à zéro après la première consommation.
        $this->assertSame(0.0, $stock);

        // Une seule trace de mouvement de vente pour l'ingrédient (commande A).
        $movementCount = app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantInventoryMovement::query()
            ->where('company_id', $company->id)
            ->where('reason_code', 'sale')
            ->where('ingredient_id', $fixture['ingredient']->id)
            ->count());

        $this->assertSame(1, $movementCount);
    }

    public function test_confirm_without_stock_level_blocked_when_policy_block(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $fixture = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'is_available' => true,
            ]);
            $ingredient = RestaurantIngredient::factory()->create(['branch_id' => $branch->id]);
            RestaurantProductIngredient::factory()->create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 1.0,
            ]);
            // Aucun niveau de stock configuré pour l'ingrédient.

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);

            return ['order' => $order, 'product' => $product];
        });

        $this->postJson("/api/v1/restaurant/orders/{$fixture['order']->id}/items", ['product_id' => $fixture['product']->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$fixture['order']->id}/submit")->assertStatus(200);

        // Confirmation : aucun niveau de stock → politique 'block' → 422.
        $this->postJson("/api/v1/restaurant/orders/{$fixture['order']->id}/confirm")->assertStatus(422);

        // La commande reste `open` (rollback complet de la transition).
        $this->assertSame('open', $fixture['order']->refresh()->status->value);
    }
}

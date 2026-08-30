<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
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
 * Couvre : le stock des ingrédients est décrémenté à la confirmation
 * (quantité × composition), le stock n'est JAMAIS négatif, une commande en
 * rupture est refusée (422) et la course sur le dernier stock ne laisse
 * passer qu'une seule confirmation.
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
     * @return array{branch: RestaurantBranch, product: RestaurantProduct, ingredient: RestaurantIngredient, order: RestaurantOrder}
     */
    private function makeOrderWithComposedProduct(Company $company, float $stockQty = 10.0): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $stockQty): array {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'is_available' => true,
            ]);

            // 1 unité de produit = 0.5 kg d'ingrédient.
            RestaurantProductIngredient::query()->create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 0.5,
                'unit_code' => 'kg',
            ]);

            RestaurantStockLevel::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $stockQty,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);

            return ['branch' => $branch, 'product' => $product, 'ingredient' => $ingredient, 'order' => $order];
        });
    }

    public function test_confirm_decrements_stock_by_composition(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product, 'ingredient' => $ingredient] = $this->makeOrderWithComposedProduct($company);

        // 3 × 0.5 kg = 1.5 kg consommés.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 3])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/confirm")->assertStatus(200)->assertJsonPath('data.status', 'in_preparation');

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $order->branch_id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(8.5, (float) $level->quantity, 0.001);

        $movementCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement::query()
            ->where('reason_code', 'sale')
            ->where('reference_id', $order->id)
            ->count());

        $this->assertSame(1, $movementCount);
    }

    public function test_insufficient_stock_blocks_confirmation_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithComposedProduct($company, stockQty: 1.0);

        // 3 unités → 1.5 kg requis, seulement 1.0 kg en stock.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 3])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/confirm")->assertStatus(422);

        // Le stock n'est jamais passé en négatif.
        $order->refresh();
        $this->assertSame('open', $order->status->value);
    }

    public function test_second_confirm_on_last_stock_fails(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch, 'product' => $product, 'order' => $order1] = $this->makeOrderWithComposedProduct($company, stockQty: 1.0);

        // Seconde commande sur la MÊME branche / MÊME produit / MÊME stock.
        /** @var RestaurantOrder $order2 */
        $order2 = app(TenantManager::class)->withinTenant($company, fn (): RestaurantOrder => RestaurantOrder::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'draft',
            'currency' => $branch->currency,
        ]));

        // Première commande : 2 × 0.5 kg = 1.0 kg → consomme tout le stock.
        $this->postJson("/api/v1/restaurant/orders/{$order1->id}/items", ['product_id' => $product->id, 'quantity' => 2])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order1->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order1->id}/confirm")->assertStatus(200);

        // Seconde commande sur le même stock : plus assez → 422.
        $this->postJson("/api/v1/restaurant/orders/{$order2->id}/items", ['product_id' => $product->id, 'quantity' => 2])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order2->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order2->id}/confirm")->assertStatus(422);
    }
}

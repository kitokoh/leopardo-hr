<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-403 (#6190) — Articles de commande : ajout, annulation, quantités.
 *
 * Couvre : prix unitaire SERVEUR (jamais accepté du client), TVA par taux
 * (rate_bps), totaux recalculés à chaque ajout/annulation, refus d'ajout
 * hors draft/open (409) et RBAC serveur+.
 */
class RestaurantOrderItemTest extends TestCase
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
     * @return array{branch: RestaurantBranch, product: RestaurantProduct, order: RestaurantOrder}
     */
    private function makeOrderWithProduct(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 1900]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'currency' => $branch->currency,
            ]);

            return ['branch' => $branch, 'product' => $product, 'order' => $order];
        });
    }

    public function test_add_item_computes_line_total_and_recalculates_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithProduct($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(201)
            ->assertJsonPath('data.unit_price_minor', 1000)
            ->assertJsonPath('data.line_total_minor', 2000)
            ->assertJsonPath('data.tax_minor', 380); // 2000 × 19 %

        $order->refresh();
        $this->assertSame(2000, (int) $order->subtotal_minor);
        $this->assertSame(380, (int) $order->tax_minor);
        $this->assertSame(2380, (int) $order->total_minor);
    }

    public function test_adding_second_item_sums_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithProduct($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 3])->assertStatus(201);

        $order->refresh();
        $this->assertSame(4000, (int) $order->subtotal_minor);
        $this->assertSame(760, (int) $order->tax_minor);
        $this->assertSame(4760, (int) $order->total_minor);
    }

    public function test_cancel_item_recalculates_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithProduct($company);

        $itemId = $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items/{$itemId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $order->refresh();
        $this->assertSame(0, (int) $order->subtotal_minor);
        $this->assertSame(0, (int) $order->total_minor);
    }

    public function test_add_item_refused_on_in_preparation_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithProduct($company);

        // Commande soumise puis confirmée (en préparation).
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/confirm")->assertStatus(200);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(409);
    }

    public function test_unavailable_product_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makeOrderWithProduct($company);

        $productId = app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantProduct::factory()->create([
            'is_available' => false,
        ])->id);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $productId, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_ordinary_employee_cannot_add_item(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithProduct($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(403);
    }
}

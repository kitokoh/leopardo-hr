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
 * RESTO-410 (#6197) — File cuisine (écran : liste, start/ready).
 *
 * Couvre : le cuisinier ne voit que les commandes de SA branche (branch_id
 * obligatoire + tenant-scope), les statuts en préparation/prête uniquement,
 * les transitions start/ready par rôle kitchen (server → 403) et
 * l'isolation cross-tenant (branche d'un autre tenant → 404).
 */
class RestaurantKitchenQueueTest extends TestCase
{
    use RefreshTenantDatabase;

    private function kitchen(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
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
     * @return array{branch: RestaurantBranch, orderInPreparation: RestaurantOrder, orderReady: RestaurantOrder, orderOpen: RestaurantOrder}
     */
    private function makeKitchenData(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            $orderInPreparation = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'in_preparation', 'currency' => $branch->currency]);
            $orderReady = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'ready', 'currency' => $branch->currency]);
            $orderOpen = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'open', 'currency' => $branch->currency]);

            foreach ([$orderInPreparation, $orderReady] as $order) {
                \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem::query()->create([
                    'company_id' => $company->id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price_minor' => 1000,
                    'line_total_minor' => 1000,
                    'tax_minor' => 0,
                    'status' => 'active',
                    'line_index' => 1,
                ]);
            }

            return [
                'branch' => $branch,
                'orderInPreparation' => $orderInPreparation,
                'orderReady' => $orderReady,
                'orderOpen' => $orderOpen,
            ];
        });
    }

    public function test_kitchen_sees_only_its_branch_orders_in_kitchen_statuses(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kitchen($company);
        ['branch' => $branch, 'orderInPreparation' => $orderInPreparation, 'orderReady' => $orderReady, 'orderOpen' => $orderOpen] = $this->makeKitchenData($company);

        $response = $this->getJson("/api/v1/restaurant/kitchen/orders?branch_id={$branch->id}")
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($orderInPreparation->id, $ids);
        $this->assertContains($orderReady->id, $ids);
        $this->assertNotContains($orderOpen->id, $ids); // open n'est pas en cuisine
        $this->assertCount(2, $ids);
    }

    public function test_kitchen_list_requires_branch_id(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kitchen($company);

        $this->getJson('/api/v1/restaurant/kitchen/orders')->assertStatus(422);
    }

    public function test_kitchen_cannot_list_other_tenant_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kitchen($company);

        /** @var RestaurantBranch $otherBranch */
        $otherBranch = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): RestaurantBranch => RestaurantBranch::factory()->create()
        );

        $this->getJson("/api/v1/restaurant/kitchen/orders?branch_id={$otherBranch->id}")->assertStatus(404);
    }

    public function test_kitchen_can_start_and_ready_an_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kitchen($company);
        ['branch' => $branch] = $this->makeKitchenData($company);

        /** @var RestaurantOrder $openOrder */
        $openOrder = app(TenantManager::class)->withinTenant($company, fn (): RestaurantOrder => RestaurantOrder::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'open',
            'currency' => $branch->currency,
        ]));

        \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem::query()->create([
            'company_id' => $company->id,
            'order_id' => $openOrder->id,
            'product_id' => RestaurantProduct::factory()->create(['branch_id' => $branch->id, 'currency' => $branch->currency, 'is_available' => true])->id,
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'tax_minor' => 0,
            'status' => 'active',
            'line_index' => 1,
        ]);

        $this->postJson("/api/v1/restaurant/kitchen/orders/{$openOrder->id}/start")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation');

        $this->postJson("/api/v1/restaurant/kitchen/orders/{$openOrder->id}/ready")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_server_cannot_use_kitchen_transitions(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch, 'orderInPreparation' => $order] = $this->makeKitchenData($company);

        $this->postJson("/api/v1/restaurant/kitchen/orders/{$order->id}/start")->assertStatus(403);
    }
}

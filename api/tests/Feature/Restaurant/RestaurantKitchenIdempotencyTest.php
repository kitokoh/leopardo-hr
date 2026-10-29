<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * #8448 — le geste cuisine venu du KDS Edge hors ligne est IDEMPOTENT.
 *
 * Le scénario d'acceptation de l'issue est : « coupure internet pendant le
 * service → la cuisine continue, resync sans perte ». Côté serveur, la
 * propriété qui rend cela sûr est qu'un rejeu ne fait pas avancer la commande
 * deux fois. Ces tests la figent — ils échouent sur un endpoint qui se
 * contenterait de déléguer à `TransitionOrderAction` sans journal.
 */
final class RestaurantKitchenIdempotencyTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

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

    /**
     * @return array{branch: RestaurantBranch, order: RestaurantOrder}
     */
    private function makeOrder(Company $company, string $status = 'open'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => $status,
                'currency' => $branch->currency,
            ]);

            RestaurantOrderItem::query()->create([
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

            return ['branch' => $branch, 'order' => $order];
        });
    }

    public function test_a_replayed_action_does_not_advance_the_order_twice(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $kitchen = $this->kitchen($company);
        ['branch' => $branch, 'order' => $order] = $this->makeOrder($company);
        $this->assignResourceAccess($kitchen, 'restaurant_branch', $branch->id, 'operate');

        $payload = [
            'client_action_id' => 'kds-action-0001',
            'order_id' => $order->id,
            'action' => 'start',
        ];

        $this->postJson('/api/v1/restaurant/kitchen/actions', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation')
            ->assertJsonPath('meta.replayed', false);

        // Le rejeu : même client_action_id, même geste. La commande ne doit
        // PAS repasser par la machine à états (elle est déjà en préparation,
        // une seconde transition lèverait 409).
        $this->postJson('/api/v1/restaurant/kitchen/actions', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation')
            ->assertJsonPath('meta.replayed', true);

        $this->assertSame(
            1,
            DB::table('restaurant_kitchen_actions')
                ->where('client_action_id', 'kds-action-0001')
                ->count(),
        );
    }

    public function test_two_distinct_actions_advance_the_order_twice(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $kitchen = $this->kitchen($company);
        ['branch' => $branch, 'order' => $order] = $this->makeOrder($company);
        $this->assignResourceAccess($kitchen, 'restaurant_branch', $branch->id, 'operate');

        $this->postJson('/api/v1/restaurant/kitchen/actions', [
            'client_action_id' => 'kds-a',
            'order_id' => $order->id,
            'action' => 'start',
        ])->assertStatus(200)->assertJsonPath('data.status', 'in_preparation');

        $this->postJson('/api/v1/restaurant/kitchen/actions', [
            'client_action_id' => 'kds-b',
            'order_id' => $order->id,
            'action' => 'ready',
        ])->assertStatus(200)->assertJsonPath('data.status', 'ready');
    }

    public function test_an_unknown_action_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $kitchen = $this->kitchen($company);
        ['branch' => $branch, 'order' => $order] = $this->makeOrder($company);
        $this->assignResourceAccess($kitchen, 'restaurant_branch', $branch->id, 'operate');

        $this->postJson('/api/v1/restaurant/kitchen/actions', [
            'client_action_id' => 'kds-x',
            'order_id' => $order->id,
            'action' => 'teleport',
        ])->assertStatus(422);
    }

    public function test_an_order_from_another_tenant_is_a_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kitchen($company);

        /** @var RestaurantOrder $foreignOrder */
        $foreignOrder = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): RestaurantOrder => RestaurantOrder::factory()->create(['status' => 'open']),
        );

        $this->postJson('/api/v1/restaurant/kitchen/actions', [
            'client_action_id' => 'kds-foreign',
            'order_id' => $foreignOrder->id,
            'action' => 'start',
        ])->assertStatus(404);
    }

    public function test_a_server_role_cannot_apply_a_kitchen_action(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        /** @var Employee $server */
        $server = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);
        Sanctum::actingAs($server);

        ['branch' => $branch, 'order' => $order] = $this->makeOrder($company);

        // Pas d'assignation `operate` sur la succursale : c'est la garde
        // ressource-scopée (#7599) qui refuse, comme pour les transitions
        // start/ready existantes (RestaurantKitchenQueueTest).
        $this->postJson('/api/v1/restaurant/kitchen/actions', [
            'client_action_id' => 'kds-server',
            'order_id' => $order->id,
            'action' => 'start',
        ])->assertStatus(403);
    }
}

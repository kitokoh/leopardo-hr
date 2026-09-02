<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-805 (#6226) — Boutique en ligne publique (jeton signé par tenant).
 *
 * Couvre : jeton requis/invalide (401), aucune fuite cross-tenant via le menu
 * public (critère d'acceptation), commande en ligne (source online, totaux
 * serveur, idempotence), suivi public par référence et paiement cash
 * (confirmé immédiat) / mobile money (pending → callback).
 */
class RestaurantPublicShopTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeTenant(string $slug): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        $plainToken = 'rshop_test_'.strrev($slug);

        $data = app(TenantManager::class)->withinTenant($company, function () use ($plainToken): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => currentCompany()->id,
                'token_hash' => RestaurantPublicShopToken::hash($plainToken),
                'name' => 'Public shop test',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create();
            $category = RestaurantCategory::factory()->create(['name' => 'Plats']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => null,
                'category_id' => $category->id,
                'price_minor' => 2500,
                'currency' => 'XAF',
                'is_available' => true,
                'status' => 'active',
            ]);
            $unavailable = RestaurantProduct::factory()->create([
                'branch_id' => null,
                'category_id' => $category->id,
                'is_available' => false,
                'status' => 'active',
            ]);

            return [
                'company' => currentCompany(),
                'branch' => $branch,
                'category' => $category,
                'product' => $product,
                'unavailable' => $unavailable,
            ];
        });

        $data['plain_token'] = $plainToken;

        return $data;
    }

    public function test_menu_requires_token(): void
    {
        $this->getJson('/api/v1/public/restaurant/menu')->assertStatus(401);
    }

    public function test_menu_rejects_invalid_token(): void
    {
        $this->withHeader('X-Restaurant-Shop-Token', 'rshop_invalid')
            ->getJson('/api/v1/public/restaurant/menu')
            ->assertStatus(401);
    }

    public function test_menu_returns_only_current_tenant_products(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');

        // Le menu du tenant A ne contient que les produits de A.
        $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson('/api/v1/public/restaurant/menu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Plats')
            ->assertJsonPath('data.0.products.0.id', $a['product']->id)
            ->assertJsonPath('data.0.products.0.price_minor', 2500)
            ->assertJsonPath('data.0.products.0.currency', 'XAF')
            ->assertJsonMissingPath('data.0.products.1.id', $b['product']->id);

        // Les produits indisponibles sont exclus du menu public.
        $names = collect($this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson('/api/v1/public/restaurant/menu')
            ->json('data.0.products'))->pluck('id')->all();
        $this->assertNotContains($a['unavailable']->id, $names);
        $this->assertNotContains($b['product']->id, $names);
    }

    public function test_online_order_is_created_with_server_totals(): void
    {
        $tenant = $this->makeTenant('alpha');

        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $tenant['branch']->id,
                'order_type' => 'takeaway',
                'items' => [
                    ['product_id' => $tenant['product']->id, 'quantity' => 2],
                ],
                'idempotency_key' => '4f8f6b7e-0000-4000-8000-000000000001',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.source', 'online')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.order_type', 'takeaway')
            ->assertJsonPath('data.subtotal_minor', 5000)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('restaurant_orders', [
            'source' => 'online',
            'subtotal_minor' => 5000,
        ]);
    }

    public function test_online_order_replay_is_idempotent(): void
    {
        $tenant = $this->makeTenant('alpha');
        $key = '4f8f6b7e-0000-4000-8000-000000000002';

        $first = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $tenant['branch']->id,
                'items' => [
                    ['product_id' => $tenant['product']->id, 'quantity' => 1],
                ],
                'idempotency_key' => $key,
            ])
            ->assertStatus(201);

        $replay = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $tenant['branch']->id,
                'items' => [
                    ['product_id' => $tenant['product']->id, 'quantity' => 1],
                ],
                'idempotency_key' => $key,
            ])
            ->assertStatus(201);

        $this->assertSame($first->json('data.reference'), $replay->json('data.reference'));
        $this->assertDatabaseCount('restaurant_orders', 1);
    }

    public function test_public_tracking_is_tenant_scoped(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');

        $order = $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $a['branch']->id,
                'items' => [['product_id' => $a['product']->id, 'quantity' => 1]],
            ])
            ->assertStatus(201)
            ->json('data.reference');

        // Le tenant B ne voit pas la commande du tenant A (404 sûr).
        $this->withHeader('X-Restaurant-Shop-Token', $b['plain_token'])
            ->getJson("/api/v1/public/restaurant/orders/{$order}")
            ->assertStatus(404);

        $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson("/api/v1/public/restaurant/orders/{$order}")
            ->assertOk()
            ->assertJsonPath('data.reference', $order);
    }

    public function test_cash_payment_confirms_order_immediately(): void
    {
        $tenant = $this->makeTenant('alpha');

        $order = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $tenant['branch']->id,
                'items' => [['product_id' => $tenant['product']->id, 'quantity' => 1]],
            ])
            ->json('data');

        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson("/api/v1/public/restaurant/orders/{$order['reference']}/pay", [
                'provider_code' => 'cash',
                'amount_minor' => $order['total_minor'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('restaurant_orders', [
            'reference' => $order['reference'],
            'status' => 'paid',
        ]);
    }

    public function test_mobile_money_payment_stays_pending_until_callback(): void
    {
        $tenant = $this->makeTenant('alpha');

        $order = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson('/api/v1/public/restaurant/orders', [
                'branch_id' => $tenant['branch']->id,
                'items' => [['product_id' => $tenant['product']->id, 'quantity' => 1]],
            ])
            ->json('data');

        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson("/api/v1/public/restaurant/orders/{$order['reference']}/pay", [
                'provider_code' => 'mobile_money',
                'amount_minor' => $order['total_minor'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('restaurant_order_payments', [
            'provider_code' => 'mobile_money',
            'status' => 'pending',
        ]);
    }

    public function test_manager_can_rotate_public_shop_token(): void
    {
        $tenant = $this->makeTenant('alpha');

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $tenant['company']->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);
        Sanctum::actingAs($employee);

        $rotated = $this->postJson('/api/v1/restaurant/public-shop-token/rotate')
            ->assertOk()
            ->json('data');

        $this->assertStringStartsWith('rshop_', $rotated['token']);

        // L'ancien jeton est invalidé, le nouveau fonctionne.
        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->getJson('/api/v1/public/restaurant/menu')
            ->assertStatus(401);

        $this->withHeader('X-Restaurant-Shop-Token', $rotated['token'])
            ->getJson('/api/v1/public/restaurant/menu')
            ->assertOk();
    }
}

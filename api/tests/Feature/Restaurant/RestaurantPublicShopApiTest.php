<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-805 (#6226) — Commande en ligne publique (menu public par tenant,
 * jeton signé, paiement).
 *
 * Couvre : rotation du jeton par un manager, menu public, création de
 * commande idempotente, et surtout l'isolation cross-tenant — un jeton du
 * tenant A ne donne JAMAIS accès aux données/produits du tenant B
 * (critère d'acceptation « aucun accès inter-tenant via le menu public »).
 */
class RestaurantPublicShopApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{token: string, branch: RestaurantBranch, product: RestaurantProduct}
     */
    private function shopContext(Company $company): array
    {
        $plain = 'rshop_'.bin2hex(random_bytes(20));

        return app(TenantManager::class)->withinTenant($company, function () use ($plain, $company): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => RestaurantPublicShopToken::hash($plain),
                'name' => 'default',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => 'XAF',
                'is_available' => true,
            ]);

            return ['token' => $plain, 'branch' => $branch, 'product' => $product];
        });
    }

    public function test_manager_rotates_shop_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $this->postJson('/api/v1/restaurant/shop/token/rotate')
            ->assertStatus(200)
            ->assertJsonPath('data.active', true)
            ->assertJsonStructure(['data' => ['id', 'token', 'active']]);
    }

    public function test_public_menu_is_tenant_scoped(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);
        $this->activateRestaurant($companyB);

        $ctxA = $this->shopContext($companyA);
        $ctxB = $this->shopContext($companyB);

        // Le menu du tenant A ne contient que les produits de A (pas ceux de B).
        $this->withHeader('X-Restaurant-Shop-Token', $ctxA['token'])
            ->getJson('/api/v1/public/restaurant/shop/menu')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.code', $ctxA['product']->code);

        // Même avec le jeton de A, impossible de lire les produits de B.
        $this->withHeader('X-Restaurant-Shop-Token', $ctxA['token'])
            ->getJson('/api/v1/public/restaurant/shop/menu')
            ->assertOk()
            ->assertJsonMissing(['code' => $ctxB['product']->code]);
    }

    public function test_invalid_or_missing_token_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->shopContext($company);

        $this->getJson('/api/v1/public/restaurant/shop/menu')->assertStatus(401);
        $this->withHeader('X-Restaurant-Shop-Token', 'rshop_invalid')
            ->getJson('/api/v1/public/restaurant/shop/menu')->assertStatus(401);
    }

    public function test_public_order_creation_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $ctx = $this->shopContext($company);

        $payload = [
            'branch_id' => $ctx['branch']->id,
            'idempotency_key' => 'web-order-'.bin2hex(random_bytes(8)),
            'items' => [
                ['product_code' => $ctx['product']->code, 'quantity' => 2],
            ],
        ];

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.total_minor', 3000);

        // Rejeu : même commande, pas de doublon.
        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.created', false);
    }

    public function test_cross_tenant_token_cannot_read_other_tenant_order(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);
        $this->activateRestaurant($companyB);

        $ctxA = $this->shopContext($companyA);
        $ctxB = $this->shopContext($companyB);

        $reference = $this->withHeader('X-Restaurant-Shop-Token', $ctxB['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', [
                'branch_id' => $ctxB['branch']->id,
                'items' => [['product_code' => $ctxB['product']->code, 'quantity' => 1]],
            ])
            ->assertStatus(201)
            ->json('data.reference');

        // Le jeton de A ne peut pas suivre une commande de B.
        $this->withHeader('X-Restaurant-Shop-Token', $ctxA['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$reference)
            ->assertStatus(404);
    }
}

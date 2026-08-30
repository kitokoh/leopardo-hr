<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-807 (#6228) — Kiosque libre-service (commande + paiement).
 *
 * Implémentation v1 : le kiosque consomme les contrats de la boutique
 * publique (jeton signé par tenant) — menu, commande avec ticket court,
 * suivi. Couvre : création de commande kiosque (ticket_number), isolation
 * cross-tenant du suivi, jeton invalide → 401.
 */
class RestaurantKioskTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{token: string, branch: RestaurantBranch, product: RestaurantProduct}
     */
    private function kioskContext(Company $company): array
    {
        $plain = 'rshop_'.bin2hex(random_bytes(20));

        return app(TenantManager::class)->withinTenant($company, function () use ($plain, $company): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => RestaurantPublicShopToken::hash($plain),
                'name' => 'kiosk',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'code' => 'PLAT-JOUR',
                'price_minor' => 2000,
                'currency' => 'XAF',
                'is_available' => true,
            ]);

            return ['token' => $plain, 'branch' => $branch, 'product' => $product];
        });
    }

    public function test_kiosk_creates_order_with_ticket_number(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $ctx = $this->kioskContext($company);

        $response = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/kiosk/orders', [
                'branch_id' => $ctx['branch']->id,
                'items' => [['product_code' => 'PLAT-JOUR', 'quantity' => 1]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.total_minor', 2000)
            ->assertJsonStructure(['data' => ['reference', 'ticket_number']]);

        $reference = $response->json('data.reference');

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/kiosk/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_kiosk_menu_is_tenant_scoped(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $ctx = $this->kioskContext($company);

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/kiosk/menu')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.code', 'PLAT-JOUR');
    }

    public function test_kiosk_rejects_invalid_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->kioskContext($company);

        $this->withHeader('X-Restaurant-Shop-Token', 'rshop_invalid')
            ->postJson('/api/v1/public/restaurant/kiosk/orders', [
                'items' => [['product_code' => 'PLAT-JOUR', 'quantity' => 1]],
            ])
            ->assertStatus(401);
    }
}

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
 * (confirmé immédiatement) / mobile money (pending → callback).
 *
 * ## Chemins réels (corrigés le 2026-10-29)
 *
 * Ce fichier ciblait `/api/v1/public/restaurant/menu` et
 * `/api/v1/public/restaurant/orders`, deux chemins qui n'existent plus : la
 * surface publique a été scindée en `/public/restaurant/shop/*` (boutique) et
 * `/public/restaurant/kiosk/*` (kiosque), et le front (`front/web/src/app/
 * shop/page.tsx`) consomme bien `/public/restaurant/shop/*`. Les 7 tests
 * échouaient donc en 404 — ils ne testaient plus rien, ils constataient une
 * URL morte. Réécrits contre le contrat servi.
 *
 * Le contrat de réponse a changé lui aussi : `menu` renvoie
 * `{categories, products, pagination}` (pas une liste de catégories
 * imbriquées), et `storeOrder` renvoie `{reference, status, total_minor,
 * currency, created, tracking_secret, track_url}` — pas la commande entière.
 */
class RestaurantPublicShopTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MENU = '/api/v1/public/restaurant/shop/menu';

    private const ORDERS = '/api/v1/public/restaurant/shop/orders';

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

            // Devise cohérente avec la société : la factory de branche vaut
            // DZD par défaut, ce qui produisait des commandes XAF sur une
            // succursale DZD.
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
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
        $this->getJson(self::MENU)->assertStatus(401);
    }

    public function test_menu_rejects_invalid_token(): void
    {
        $this->withHeader('X-Restaurant-Shop-Token', 'rshop_invalid')
            ->getJson(self::MENU)
            ->assertStatus(401);
    }

    public function test_menu_returns_only_current_tenant_products(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');

        // Le menu du tenant A ne contient que les produits de A.
        $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson(self::MENU)
            ->assertOk()
            ->assertJsonPath('data.products.0.id', $a['product']->id)
            ->assertJsonPath('data.products.0.price_minor', 2500)
            ->assertJsonPath('data.products.0.currency', 'XAF');

        // Les produits indisponibles sont exclus du menu public.
        $ids = collect($this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson(self::MENU)
            ->json('data.products'))->pluck('id')->all();

        $this->assertContains($a['product']->id, $ids);
        $this->assertNotContains($a['unavailable']->id, $ids);
        $this->assertNotContains($b['product']->id, $ids);
    }

    public function test_online_order_is_created_with_server_totals(): void
    {
        $tenant = $this->makeTenant('alpha');

        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, [
                'branch_id' => $tenant['branch']->id,
                'items' => [
                    ['product_code' => $tenant['product']->code, 'quantity' => 2],
                ],
                'idempotency_key' => '4f8f6b7e-0000-4000-8000-000000000001',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.created', true);

        // Les totaux sont calculés SERVEUR : le prix du payload n'est jamais
        // accepté tel quel (2 × 2500 = 5000 de sous-total, pas un montant
        // fourni par le client). Le total ajoute la TVA du produit — on
        // l'assère en base plutôt qu'en dur, le taux venant de la factory.
        $this->assertDatabaseHas('restaurant_orders', [
            'subtotal_minor' => 5000,
            'currency' => 'XAF',
        ]);

        $order = \App\Modules\RestaurantManager\Domain\Models\RestaurantOrder::query()
            ->where('subtotal_minor', 5000)
            ->firstOrFail();

        $this->assertSame(5000 + $order->tax_minor, $order->total_minor);
    }

    public function test_online_order_replay_is_idempotent(): void
    {
        $tenant = $this->makeTenant('alpha');
        $key = '4f8f6b7e-0000-4000-8000-000000000002';

        $payload = [
            'branch_id' => $tenant['branch']->id,
            'items' => [
                ['product_code' => $tenant['product']->code, 'quantity' => 1],
            ],
            'idempotency_key' => $key,
        ];

        $first = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, $payload)
            ->assertStatus(201);

        // Rejeu : même référence, 200 (pas 201), aucun doublon.
        $replay = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.created', false);

        $this->assertSame($first->json('data.reference'), $replay->json('data.reference'));
        $this->assertDatabaseCount('restaurant_orders', 1);
    }

    public function test_public_tracking_is_tenant_scoped(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');

        $created = $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->postJson(self::ORDERS, [
                'branch_id' => $a['branch']->id,
                'items' => [['product_code' => $a['product']->code, 'quantity' => 1]],
            ])
            ->assertStatus(201)
            ->json('data');

        $reference = $created['reference'];
        $secret = $created['tracking_secret'];

        // Le tenant B ne voit pas la commande du tenant A (404 sûr).
        $this->withHeader('X-Restaurant-Shop-Token', $b['plain_token'])
            ->getJson(self::ORDERS.'/'.$reference.'?secret='.$secret)
            ->assertStatus(404);

        // Le tenant A la voit, avec le secret de suivi remis à la création.
        $this->withHeader('X-Restaurant-Shop-Token', $a['plain_token'])
            ->getJson(self::ORDERS.'/'.$reference.'?secret='.$secret)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    public function test_tracking_without_secret_is_not_found(): void
    {
        $tenant = $this->makeTenant('alpha');

        $reference = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, [
                'branch_id' => $tenant['branch']->id,
                'items' => [['product_code' => $tenant['product']->code, 'quantity' => 1]],
            ])
            ->json('data.reference');

        // BOS-050 (#8208) : le secret de suivi est obligatoire pour les
        // commandes créées après la tranche 7 — une référence seule ne suffit
        // plus (elle était énumérable).
        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->getJson(self::ORDERS.'/'.$reference)
            ->assertStatus(404);
    }

    public function test_cash_payment_returns_pay_at_pickup(): void
    {
        $tenant = $this->makeTenant('alpha');

        $order = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, [
                'branch_id' => $tenant['branch']->id,
                'items' => [['product_code' => $tenant['product']->code, 'quantity' => 1]],
            ])
            ->json('data');

        // Le cash n'est PAS encaissé en ligne : la commande est réglée au
        // point de retrait, aucun paiement n'est créé.
        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS.'/'.$order['reference'].'/pay', [
                'provider_code' => 'cash',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider_code', 'cash')
            ->assertJsonPath('data.instruction', 'pay_at_pickup');

        $this->assertDatabaseCount('restaurant_order_payments', 0);
    }

    public function test_mobile_money_payment_stays_pending_until_callback(): void
    {
        $tenant = $this->makeTenant('alpha');

        $order = $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS, [
                'branch_id' => $tenant['branch']->id,
                'items' => [['product_code' => $tenant['product']->code, 'quantity' => 1]],
            ])
            ->json('data');

        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->postJson(self::ORDERS.'/'.$order['reference'].'/pay', [
                'provider_code' => 'mobile_money',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.provider_code', 'mobile_money')
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
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($employee);

        $rotated = $this->postJson('/api/v1/restaurant/shop/token/rotate')
            ->assertOk()
            ->json('data');

        $this->assertStringStartsWith('rshop_', $rotated['token']);

        // L'ancien jeton est invalidé, le nouveau fonctionne.
        $this->withHeader('X-Restaurant-Shop-Token', $tenant['plain_token'])
            ->getJson(self::MENU)
            ->assertStatus(401);

        $this->withHeader('X-Restaurant-Shop-Token', $rotated['token'])
            ->getJson(self::MENU)
            ->assertOk();
    }
}

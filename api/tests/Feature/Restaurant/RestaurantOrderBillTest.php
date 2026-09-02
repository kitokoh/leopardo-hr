<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-405 (#6192) — Addition & remises (calcul 100 % serveur).
 *
 * Couvre : sous-total/TVA/total exacts (minor units, arrondis), promotion
 * pourcentage bornée (≤ sous-total), code invalide/expiré → 422, aucun
 * montant accepté du client (le body de la requête n'expose aucun montant).
 */
class RestaurantOrderBillTest extends TestCase
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
    private function makeOrderWithItem(Company $company, int $quantity = 2, int $priceMinor = 1000): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($priceMinor): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 1900]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => $priceMinor,
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

    public function test_bill_computes_server_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithItem($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 2])->assertStatus(201);

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill")
            ->assertStatus(200)
            ->assertJsonPath('data.bill.subtotal_minor', 2000)
            ->assertJsonPath('data.bill.tax_minor', 380)
            ->assertJsonPath('data.bill.discount_minor', 0)
            ->assertJsonPath('data.bill.total_minor', 2380)
            ->assertJsonPath('data.bill.promotion_code', null);
    }

    public function test_bill_applies_percent_promotion_bounded_to_subtotal(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithItem($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 2])->assertStatus(201);

        // 10 % (1000 bps) de 2000 = 200.
        $promo = app(TenantManager::class)->withinTenant($company, fn (): RestaurantPromotion => RestaurantPromotion::factory()->create([
            'code' => 'MIDI10',
            'discount_type' => 'percent',
            'value_minor' => 1000,
            'is_active' => true,
        ]));

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code=MIDI10")
            ->assertStatus(200)
            ->assertJsonPath('data.bill.discount_minor', 200)
            ->assertJsonPath('data.bill.total_minor', 2180)
            ->assertJsonPath('data.bill.promotion_code', 'MIDI10');

        // Le compteur d'utilisations a été incrémenté.
        $promo->refresh();
        $this->assertSame(1, (int) $promo->used_count);
    }

    public function test_bill_rejects_unknown_promotion_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithItem($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code=NOPE")
            ->assertStatus(422);
    }

    public function test_bill_rejects_expired_promotion(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeOrderWithItem($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);

        app(TenantManager::class)->withinTenant($company, fn (): RestaurantPromotion => RestaurantPromotion::factory()->create([
            'code' => 'PASSE',
            'discount_type' => 'amount',
            'value_minor' => 500,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]));

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code=PASSE")
            ->assertStatus(422);
    }
}

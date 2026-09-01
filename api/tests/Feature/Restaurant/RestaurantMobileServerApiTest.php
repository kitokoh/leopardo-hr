<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-801 (#6222) — App mobile serveur : prise de commande, service,
 * encaissement cash.
 *
 * Couvre : file de service (commandes actives), tables occupées, transition
 * « servie », encaissement cash (montant vérifié serveur, idempotence) et
 * isolation cross-tenant (404 sûr).
 */
class RestaurantMobileServerApiTest extends TestCase
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
    private function openOrder(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $session = RestaurantPosSession::factory()->create(['branch_id' => $branch->id]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => 'XAF',
                'is_available' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'pos_session_id' => $session->id,
                'order_type' => 'takeaway',
                'status' => 'ready',
                'source' => 'pos',
                'currency' => 'XAF',
                'total_minor' => 1000,
            ]);

            RestaurantOrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
            ]);

            return ['branch' => $branch, 'product' => $product, 'order' => $order];
        });
    }

    public function test_server_lists_active_orders_and_tables(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $ctx = $this->openOrder($company);

        $this->getJson('/api/v1/restaurant/mobile/server/orders')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $ctx['order']->reference)
            ->assertJsonPath('data.0.status', 'ready');

        $this->getJson('/api/v1/restaurant/mobile/server/tables')->assertOk();
    }

    public function test_server_can_serve_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $ctx = $this->openOrder($company);

        $this->postJson('/api/v1/restaurant/mobile/server/orders/'.$ctx['order']->id.'/serve')
            ->assertOk()
            ->assertJsonPath('data.status', 'served');

        // Idempotent : déjà servie.
        $this->postJson('/api/v1/restaurant/mobile/server/orders/'.$ctx['order']->id.'/serve')
            ->assertOk()
            ->assertJsonPath('data.status', 'served');
    }

    public function test_server_can_pay_cash_with_idempotency(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $ctx = $this->openOrder($company);

        $payload = [
            'amount_minor' => 1000,
            'tip_minor' => 100,
            'idempotency_key' => 'cash-'.bin2hex(random_bytes(8)),
        ];

        $this->postJson('/api/v1/restaurant/mobile/server/orders/'.$ctx['order']->id.'/pay', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.amount_minor', 1000);

        // Rejeu → même paiement, jamais de doublon.
        $this->postJson('/api/v1/restaurant/mobile/server/orders/'.$ctx['order']->id.'/pay', $payload)
            ->assertOk();
    }

    public function test_cross_tenant_order_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);
        $this->activateRestaurant($companyB);

        $ctx = $this->openOrder($companyA);
        $this->server($companyB);

        $this->postJson('/api/v1/restaurant/mobile/server/orders/'.$ctx['order']->id.'/serve')
            ->assertStatus(404);
    }
}

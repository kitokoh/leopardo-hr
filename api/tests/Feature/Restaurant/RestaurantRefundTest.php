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
 * RESTO-408 (#6195) — Remboursements (motif, idempotence, événement).
 *
 * Couvre : réservé `restaurant.manage` (serveur → 403), remboursement d'une
 * commande payée → processed + événement outbox, double remboursement
 * impossible (422), rejeu idempotent, remboursement d'une commande non payée
 * → 409.
 */
class RestaurantRefundTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
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
     * Crée une commande PAYÉE (total 1500, paiement cash confirmé).
     */
    private function makePaidOrder(Company $company): RestaurantOrder
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): RestaurantOrder {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'paid',
                'currency' => $branch->currency,
                'total_minor' => 1500,
            ]);

            \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1500,
                'line_total_minor' => 1500,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'provider_code' => 'cash',
                'amount_minor' => 1500,
                'currency' => 'DZD',
                'status' => 'confirmed',
                'paid_at' => now(),
                'provider_reference' => 'CASH-TEST',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            return $order;
        });
    }

    public function test_principal_can_refund_paid_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        $order = $this->makePaidOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.reason_code', 'customer_request');

        $order->refresh();
        $this->assertSame('refunded', $order->status->value);

        $events = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.payment.refunded.v1')
            ->count());

        $this->assertSame(1, $events);
    }

    public function test_double_refund_is_impossible(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        $order = $this->makePaidOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
        ])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
        ])->assertStatus(422);
    }

    public function test_refund_replay_with_same_idempotency_key_returns_same_refund(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        $order = $this->makePaidOrder($company);

        $key = (string) \Illuminate\Support\Str::uuid();

        $first = $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $replay = $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
    }

    public function test_server_cannot_refund(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $order = $this->makePaidOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 1500,
            'reason_code' => 'customer_request',
        ])->assertStatus(403);
    }

    public function test_refund_on_unpaid_order_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        /** @var RestaurantOrder $order */
        $order = app(TenantManager::class)->withinTenant($company, fn (): RestaurantOrder => RestaurantOrder::factory()->create([
            'status' => 'open',
        ]));

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/refund", [
            'amount_minor' => 100,
            'reason_code' => 'customer_request',
        ])->assertStatus(409);
    }
}

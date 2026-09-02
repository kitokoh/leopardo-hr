<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-605 (#6210) — Livreurs + cycle de livraison.
 *
 * Couvre le CRUD des livreurs, le cycle complet
 * (assign → out_for_delivery → delivered), le refus d'un livreur inactif et
 * le retour de la commande à `ready` quand la livraison est annulée.
 */
class RestaurantDeliveryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_delivery_full_cycle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $ids = app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $rider = RestaurantDeliveryRider::factory()->create(['branch_id' => $branch->id]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'order_type' => 'delivery',
                'status' => 'ready',
            ]);

            $this->postJson('/api/v1/restaurant/delivery-riders', [
                'branch_id' => $branch->id,
                'name' => 'Yannick',
                'phone' => '+237600000004',
                'is_active' => true,
            ])->assertStatus(201);

            $this->postJson('/api/v1/restaurant/deliveries', [
                'order_id' => $order->id,
                'fee_minor' => 1500,
            ])->assertStatus(201)
                ->assertJsonPath('data.status', DeliveryStatus::PENDING->value);

            /** @var RestaurantDelivery $delivery */
            $delivery = RestaurantDelivery::query()->firstOrFail();
            $this->assertSame($order->id, $delivery->order_id);

            // Cycle complet.
            $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/assign", ['rider_id' => $rider->id])
                ->assertOk()
                ->assertJsonPath('data.status', DeliveryStatus::ASSIGNED->value);

            $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/out-for-delivery")
                ->assertOk()
                ->assertJsonPath('data.status', DeliveryStatus::OUT_FOR_DELIVERY->value);

            $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/deliver", ['delivered_to_contact' => 'Alice'])
                ->assertOk()
                ->assertJsonPath('data.status', DeliveryStatus::DELIVERED->value);

            $this->assertNotNull($delivery->refresh()->delivered_at);
            $this->assertSame('closed', $order->refresh()->status->value);

            return ['delivery' => $delivery->id];
        });
    }

    public function test_inactive_rider_cannot_be_assigned(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $rider = RestaurantDeliveryRider::factory()->create(['branch_id' => $branch->id, 'is_active' => false]);
            $order = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'order_type' => 'delivery', 'status' => 'ready']);

            $this->postJson('/api/v1/restaurant/deliveries', ['order_id' => $order->id, 'fee_minor' => 1000])->assertStatus(201);

            $delivery = RestaurantDelivery::query()->firstOrFail();

            $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/assign", ['rider_id' => $rider->id])
                ->assertStatus(422);
        });
    }

    public function test_cancelled_delivery_returns_order_to_ready(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $order = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'order_type' => 'delivery', 'status' => 'in_preparation']);

            $this->postJson('/api/v1/restaurant/deliveries', ['order_id' => $order->id, 'fee_minor' => 1000])->assertStatus(201);

            $delivery = RestaurantDelivery::query()->firstOrFail();

            $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/cancel")
                ->assertOk()
                ->assertJsonPath('data.status', DeliveryStatus::CANCELLED->value);

            // Critère d'acceptation : livraison annulée → commande retourne à ready.
            $this->assertSame('ready', $order->refresh()->status->value);
        });
    }
}

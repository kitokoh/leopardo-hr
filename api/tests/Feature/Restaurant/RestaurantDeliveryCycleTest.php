<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-605 (#6210) — Cycle de livraison complet.
 *
 * Couvre : création (commande à livrer, frais depuis la zone, idempotence),
 * transitions assign → out_for_delivery → deliver (commande → served),
 * annulation (commande → ready), refus livreur inactif, transitions
 * invalides (409), RBAC et isolation cross-tenant.
 */
class RestaurantDeliveryCycleTest extends TestCase
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

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    private function deliveryOrder(Company $company, RestaurantBranch $branch, string $status = 'ready'): RestaurantOrder
    {
        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_type' => 'delivery',
            'status' => $status,
            'currency' => 'XAF',
        ]);

        return $order;
    }

    public function test_full_delivery_cycle(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        /** @var RestaurantDeliveryZone $zone */
        $zone = RestaurantDeliveryZone::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'fee_minor' => 1500]);
        /** @var RestaurantDeliveryRider $rider */
        $rider = RestaurantDeliveryRider::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $order = $this->deliveryOrder($company, $branch);

        // Création : frais recalculés depuis la zone (1500), jamais du client.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/delivery", [
            'zone_id' => $zone->id,
        ])->assertStatus(201)
            ->assertJsonFragment(['status' => 'pending', 'fee_minor' => 1500]);

        $deliveryId = RestaurantDelivery::query()->where('order_id', $order->id)->firstOrFail()->id;

        // Rejeu : idempotent — même livraison.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/delivery", [
            'zone_id' => $zone->id,
        ])->assertStatus(201);

        $this->assertSame(1, RestaurantDelivery::query()->where('order_id', $order->id)->count());

        // Assign → out_for_delivery → deliver.
        $this->postJson("/api/v1/restaurant/deliveries/{$deliveryId}/assign", ['rider_id' => $rider->id])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'assigned']);

        $this->postJson("/api/v1/restaurant/deliveries/{$deliveryId}/out-for-delivery")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'out_for_delivery']);

        $this->postJson("/api/v1/restaurant/deliveries/{$deliveryId}/deliver", ['delivered_to_contact' => 'Marie'])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'delivered', 'delivered_to_contact' => 'Marie']);

        $order->refresh();
        $this->assertSame('served', $order->status->value, 'La commande livrée passe à served.');
    }

    public function test_cancelled_delivery_returns_order_to_ready(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        /** @var RestaurantDeliveryRider $rider */
        $rider = RestaurantDeliveryRider::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $order = $this->deliveryOrder($company, $branch);

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'status' => 'assigned',
            'rider_id' => $rider->id,
        ]);

        $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/cancel")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'cancelled']);

        $order->refresh();
        $this->assertSame('ready', $order->status->value, 'La commande retourne à ready après annulation.');
    }

    public function test_inactive_rider_is_refused(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        /** @var RestaurantDeliveryRider $rider */
        $rider = RestaurantDeliveryRider::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'is_active' => false]);
        $order = $this->deliveryOrder($company, $branch);

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
        ]);

        $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/assign", ['rider_id' => $rider->id])
            ->assertStatus(422);
    }

    public function test_invalid_transition_returns_409(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        $order = $this->deliveryOrder($company, $branch);

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        // pending → delivered n'est pas une transition autorisée.
        $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/deliver", ['delivered_to_contact' => 'X'])
            ->assertStatus(409);
    }

    public function test_delivery_of_other_tenant_returns_404(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var RestaurantBranch $otherBranch */
        $otherBranch = RestaurantBranch::factory()->create(['company_id' => $otherCompany->id]);
        /** @var RestaurantOrder $otherOrder */
        $otherOrder = RestaurantOrder::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'order_type' => 'delivery',
        ]);

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::factory()->create([
            'company_id' => $otherCompany->id,
            'order_id' => $otherOrder->id,
        ]);

        $this->getJson("/api/v1/restaurant/deliveries/{$delivery->id}")->assertStatus(404);
    }

    public function test_ordinary_employee_cannot_assign_delivery(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        $order = $this->deliveryOrder($company, $branch);

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
        ]);

        $this->postJson("/api/v1/restaurant/deliveries/{$delivery->id}/assign", ['rider_id' => 1])
            ->assertStatus(403);
    }

    public function test_delivery_requires_delivery_order(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        /** @var RestaurantOrder $dineIn */
        $dineIn = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
        ]);

        $this->postJson("/api/v1/restaurant/orders/{$dineIn->id}/delivery", [])
            ->assertStatus(422);
    }
}

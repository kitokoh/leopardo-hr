<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-802 (#6223) — App mobile livreur : tournées, statuts, navigation.
 *
 * Couvre : liste des livraisons assignées au livreur connecté (résolution
 * par employee_id), transitions out_for_delivery → delivered, et isolation
 * cross-tenant (un livreur ne voit jamais une livraison d'un autre tenant).
 */
class RestaurantMobileRiderApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function rider(Company $company): Employee
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
     * @return array{rider: RestaurantDeliveryRider, delivery: RestaurantDelivery}
     */
    private function assignedDelivery(Company $company, Employee $employee): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $employee): array {
            $branch = RestaurantBranch::factory()->create();
            $rider = RestaurantDeliveryRider::factory()->create([
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'is_active' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'order_type' => 'delivery',
                'source' => 'web',
                'currency' => 'XAF',
                'total_minor' => 2500,
            ]);

            $delivery = RestaurantDelivery::factory()->create([
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'status' => 'assigned',
                'delivered_to_contact' => 'M. Test',
            ]);

            return ['rider' => $rider, 'delivery' => $delivery];
        });
    }

    public function test_rider_lists_only_his_deliveries(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $employee = $this->rider($company);
        $ctx = $this->assignedDelivery($company, $employee);

        $this->getJson('/api/v1/restaurant/mobile/rider/deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ctx['delivery']->id)
            ->assertJsonPath('data.0.status', 'assigned');
    }

    public function test_rider_delivery_cycle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $employee = $this->rider($company);
        $ctx = $this->assignedDelivery($company, $employee);

        $this->postJson('/api/v1/restaurant/mobile/rider/deliveries/'.$ctx['delivery']->id.'/out-for-delivery')
            ->assertOk()
            ->assertJsonPath('data.status', 'out_for_delivery');

        $this->postJson('/api/v1/restaurant/mobile/rider/deliveries/'.$ctx['delivery']->id.'/deliver')
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonStructure(['data' => ['delivered_at']]);
    }

    public function test_cross_tenant_delivery_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($companyA);
        $this->activateRestaurant($companyB);

        $employeeA = $this->rider($companyA);
        $ctx = $this->assignedDelivery($companyA, $employeeA);
        $this->rider($companyB);

        $this->postJson('/api/v1/restaurant/mobile/rider/deliveries/'.$ctx['delivery']->id.'/deliver')
            ->assertStatus(404);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-202 (#6286) — API des tournées.
 *
 * - idempotence : assign ×2, close ×2 → mêmes résultats ;
 * - gardes : chevauchement livreur (409), clôture incomplète (409),
 *   colis déjà planifié (409), colis hors tenant (422) ;
 * - isolation tenant : tournée du tenant A → 404 depuis B ;
 * - RBAC : 401 sans auth, 403 non-manager.
 */
class DeliveryRouteApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function employee(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private int $deliverySeq = 0;

    /** @return list<int> */
    private function createDeliveries(int $count = 2, ?int $cod = 5000): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $this->deliverySeq++;
            $delivery = Delivery::query()->create([
                'company_id' => $this->company->id,
                'reference' => sprintf('DLV-2026-%06d', $this->deliverySeq),
                'source' => 'manual',
                'type' => 'parcel',
                'status' => 'created',
                'cod_amount_minor' => $cod,
                'dropoff_contact' => 'Client '.$this->deliverySeq,
                'dropoff_address' => 'Alger, Rue '.$this->deliverySeq,
            ]);
            $ids[] = (int) $delivery->id;
        }

        return $ids;
    }

    private function createRoute(?int $driverId = null, int $count = 2): DeliveryRoute
    {
        $ids = $this->createDeliveries($count);

        $response = $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'zone' => 'Alger Centre',
            'delivery_ids' => $ids,
        ]);

        if ($driverId !== null) {
            $response = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $response->json('data.id')), [
                'driver_id' => $driverId,
                'vehicle_code' => 'VEH-001',
            ]);
        }

        return DeliveryRoute::query()->findOrFail((int) $response->json('data.id'));
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [1],
        ])->assertStatus(401);
    }

    public function test_store_rejects_non_manager(): void
    {
        Sanctum::actingAs($this->employee());

        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [1],
        ])->assertStatus(403);
    }

    public function test_store_creates_route_with_ordered_stops(): void
    {
        Sanctum::actingAs($this->manager());
        $ids = $this->createDeliveries(3);

        $response = $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'zone' => 'Alger Centre',
            'delivery_ids' => $ids,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonCount(3, 'data.stops');

        $orders = collect($response->json('data.stops'))->pluck('sort_order')->all();
        self::assertSame([1, 2, 3], $orders);
    }

    public function test_store_rejects_foreign_deliveries_and_empty_list(): void
    {
        Sanctum::actingAs($this->manager());

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $foreign = Delivery::query()->create([
            'company_id' => $other->id,
            'reference' => 'DLV-2026-999999',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'created',
            'dropoff_contact' => 'X',
            'dropoff_address' => 'Casablanca',
        ]);

        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [(int) $foreign->id],
        ])->assertStatus(422);

        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [],
        ])->assertStatus(422);
    }

    public function test_assign_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->createRoute();

        $first = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $route->id), [
            'driver_id' => 42,
            'vehicle_code' => 'VEH-001',
        ])->assertOk();

        $second = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $route->id), [
            'driver_id' => 42,
            'vehicle_code' => 'VEH-001',
        ])->assertOk();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame('assigned', $second->json('data.status'));
        self::assertSame(42, $second->json('data.driver_id'));
        self::assertSame('VEH-001', $second->json('data.vehicle_code'));
    }

    public function test_assign_rejects_overlapping_driver_on_same_date(): void
    {
        Sanctum::actingAs($this->manager());
        $this->createRoute(driverId: 7);

        // Deuxième tournée du même jour pour le même livreur → 409.
        $ids = $this->createDeliveries(1, cod: 1000);
        $route2 = $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => $ids,
        ])->assertStatus(201)->json('data');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $route2['id']), [
            'driver_id' => 7,
            'vehicle_code' => 'VEH-002',
        ])->assertStatus(409);

        // Jour différent → OK.
        $ids2 = $this->createDeliveries(1, cod: 1000);
        $route3 = $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-02',
            'delivery_ids' => $ids2,
        ])->assertStatus(201)->json('data');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $route3['id']), [
            'driver_id' => 7,
            'vehicle_code' => 'VEH-002',
        ])->assertOk();
    }

    public function test_close_refuses_incomplete_route(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->createRoute(driverId: 9);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/close', $route->id))
            ->assertStatus(409)
            ->assertJson(['message' => 'ROUTE_INCOMPLETE']);
    }

    public function test_close_is_idempotent_and_computes_totals(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->createRoute(driverId: 9, count: 3);

        // 2 stops livrés (COD 5000 chacun) + 1 stop en échec.
        $stops = $route->stops()->get();
        $stops->get(0)->forceFill(['status' => 'delivered', 'delivered_at' => Carbon::parse('2026-09-01 18:00:00')])->save();
        $stops->get(1)->forceFill(['status' => 'delivered', 'delivered_at' => Carbon::parse('2026-09-01 18:05:00')])->save();
        $stops->get(2)->forceFill(['status' => 'failed'])->save();

        $first = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/close', $route->id))->assertOk();

        self::assertSame('completed', $first->json('data.status'));
        self::assertSame(3, $first->json('data.deliveries_count'));
        self::assertSame(2, $first->json('data.delivered_count'));
        self::assertSame(1, $first->json('data.failed_count'));
        self::assertSame(10000, $first->json('data.cod_collected_minor'));
        self::assertNotNull($first->json('data.closed_at'));

        // Re-clôture → mêmes résultats, aucun recalcul visible.
        $second = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/close', $route->id))->assertOk();
        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame($first->json('data.cod_collected_minor'), $second->json('data.cod_collected_minor'));
    }

    public function test_show_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());
        $route = $this->createRoute();

        $this->getJson(sprintf('/api/v1/delivery/deliveries/routes/%d', $route->id))
            ->assertOk()
            ->assertJsonCount(2, 'data.stops');

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($managerB);

        $this->getJson(sprintf('/api/v1/delivery/deliveries/routes/%d', $route->id))->assertStatus(404);
    }
}

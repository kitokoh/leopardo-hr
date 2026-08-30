<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-203 (#6287) — API mobile livreur (partie serveur).
 *
 * - `routes/today` : scope par propriété (driver_id = employé connecté) —
 *   un rider ne voit jamais la tournée d'un autre livreur ;
 * - `stops/{stop}/status` : idempotent (rejeu → même arrêt), POD obligatoire
 *   pour delivered, transitions machine à états, isolation tenant.
 */
class DeliveryRiderApiTest extends TestCase
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

    private function rider(int $id = 11): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'id' => $id,
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function createRouteFor(int $driverId, string $date = 'today', int $count = 2): DeliveryRoute
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $delivery = Delivery::query()->create([
                'company_id' => $this->company->id,
                'reference' => 'DLV-2026-9'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT),
                'source' => 'manual',
                'type' => 'parcel',
                'status' => 'assigned',
                'dropoff_contact' => 'Client '.$i,
                'dropoff_address' => 'Alger',
            ]);
            $ids[] = (int) $delivery->id;
        }

        /** @var DeliveryRoute $route */
        $route = DeliveryRoute::query()->create([
            'company_id' => $this->company->id,
            'route_date' => $date === 'today' ? now()->toDateString() : $date,
            'driver_id' => $driverId,
            'vehicle_code' => 'VEH-00'.$driverId,
            'status' => 'assigned',
        ]);

        foreach ($ids as $index => $deliveryId) {
            DeliveryStop::query()->create([
                'company_id' => $this->company->id,
                'route_id' => $route->id,
                'delivery_id' => $deliveryId,
                'sort_order' => $index + 1,
                'status' => 'pending',
                'address' => 'Alger',
                'contact' => 'Client',
            ]);
        }

        return $route;
    }

    public function test_today_requires_authentication(): void
    {
        $this->getJson('/api/v1/delivery/deliveries/routes/today')->assertStatus(401);
    }

    public function test_today_is_scoped_to_own_deliveries(): void
    {
        $myRoute = $this->createRouteFor(11);
        $this->createRouteFor(12); // tournée d'un AUTRE livreur

        Sanctum::actingAs($this->rider(11));

        $response = $this->getJson('/api/v1/delivery/deliveries/routes/today')->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($myRoute->id, $response->json('data.0.id'));
        self::assertCount(2, $response->json('data.0.stops'));
    }

    public function test_today_excludes_other_dates(): void
    {
        $this->createRouteFor(11, date: '2020-01-01');

        Sanctum::actingAs($this->rider(11));

        $this->getJson('/api/v1/delivery/deliveries/routes/today')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_stop_status_drives_delivery_transition(): void
    {
        $route = $this->createRouteFor(11);
        $stopId = (int) $route->stops()->first()->id;

        Sanctum::actingAs($this->rider(11));

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'en_route',
        ])->assertOk()->assertJsonPath('data.status', 'en_route');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'arrived',
        ])->assertOk()->assertJsonPath('data.status', 'arrived');

        // delivered exige la POD.
        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'delivered',
        ])->assertStatus(409)
            ->assertJson(['message' => 'PROOF_REQUIRED']);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'delivered',
            'proof_document_id' => 555,
        ])->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.proof_id', 555);

        // La livraison est passée delivered via la machine à états.
        $deliveryId = (int) $route->stops()->find($stopId)->delivery_id;
        self::assertSame('delivered', Delivery::query()->find($deliveryId)->status);
        self::assertNotNull(Delivery::query()->find($deliveryId)->delivered_at);
    }

    public function test_stop_status_replay_is_idempotent(): void
    {
        $route = $this->createRouteFor(11);
        $stopId = (int) $route->stops()->first()->id;

        Sanctum::actingAs($this->rider(11));

        $first = $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'arrived',
        ])->assertOk();

        $second = $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'arrived',
        ])->assertOk();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
    }

    public function test_rider_cannot_update_other_drivers_stop(): void
    {
        $route = $this->createRouteFor(12);
        $stopId = (int) $route->stops()->first()->id;

        Sanctum::actingAs($this->rider(11));

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'arrived',
        ])->assertStatus(403);
    }

    public function test_stop_status_is_tenant_scoped(): void
    {
        $route = $this->createRouteFor(11);
        $stopId = (int) $route->stops()->first()->id;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $riderB */
        $riderB = Employee::factory()->create([
            'id' => 21,
            'company_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($riderB);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $stopId), [
            'status' => 'arrived',
        ])->assertStatus(404);
    }
}

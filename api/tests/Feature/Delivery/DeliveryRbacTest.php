<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D05 (#6294) — Matrice RBAC du module Delivery (deny-by-default).
 *
 * Matrice : docs/architecture/DELIVERY_RBAC.md.
 * Garde de routes : middleware `delivery.role` ; décisions par ressource :
 * DeliveryPolicy / DeliveryRoutePolicy.
 *
 * - 401 sans authentification ; 403 employé sans rôle ; 403 rider sur les
 *   routes dispatcher ; 403 cross-employé (livreur vs tournée d'un collègue) ;
 *   ️403 cross-tenant (ressource d'un autre tenant) ; ownership rider.
 */
class DeliveryRbacTest extends TestCase
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

    private function manager(string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function employee(string $role = 'employee', ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $employee;
    }

    private function createDelivery(array $overrides = []): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create(array_merge([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'created',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ], $overrides));

        return $delivery;
    }

    private function createRoute(Employee $driver): DeliveryRoute
    {
        /** @var DeliveryRoute $route */
        $route = DeliveryRoute::query()->create([
            'company_id' => $this->company->id,
            'route_date' => now()->toDateString(),
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        $delivery = $this->createDelivery();
        // L'affectation à une tournée passe la livraison en `assigned`
        // (machine à états : created → picked_up est illégal).
        $delivery->forceFill(['status' => 'assigned'])->save();
        DeliveryStop::query()->create([
            'company_id' => $this->company->id,
            'route_id' => $route->id,
            'delivery_id' => $delivery->id,
            'sort_order' => 1,
            'status' => 'pending',
            'address' => $delivery->dropoff_address,
        ]);

        return $route;
    }

    // ── 401 — sans authentification ────────────────────────────────────────

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/delivery/deliveries')->assertStatus(401);
        $this->postJson('/api/v1/delivery/deliveries', [])->assertStatus(401);
        $this->postJson('/api/v1/delivery/deliveries/events', [])->assertStatus(401);
        $this->postJson('/api/v1/delivery/deliveries/routes', [])->assertStatus(401);
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertStatus(401);
    }

    // ── Deny-by-default — employé sans rôle delivery ───────────────────────

    public function test_marketing_manager_is_denied_everywhere(): void
    {
        Sanctum::actingAs($this->manager('marketing'));

        $this->getJson('/api/v1/delivery/deliveries')
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
        $this->postJson('/api/v1/delivery/deliveries/routes', [])
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
    }

    public function test_plain_employee_is_denied_dispatcher_routes(): void
    {
        Sanctum::actingAs($this->employee());

        // CRUD livraisons / tournées : middleware deny-by-default.
        $this->getJson('/api/v1/delivery/deliveries')
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
        $this->postJson('/api/v1/delivery/deliveries/routes', [])
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);

        // Rapports : non manager → 403.
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
    }

    // ── Riders — écriture bornée à leurs tournées ──────────────────────────

    public function test_rider_cannot_post_events_for_delivery_outside_his_route(): void
    {
        $riderA = $this->employee();
        $riderB = $this->employee();

        $routeA = $this->createRoute($riderA);
        $routeB = $this->createRoute($riderB);

        $deliveryA = DeliveryStop::query()->where('route_id', $routeA->id)->firstOrFail()->delivery;
        $deliveryB = DeliveryStop::query()->where('route_id', $routeB->id)->firstOrFail()->delivery;

        // Rider A : autorisé sur SA livraison.
        Sanctum::actingAs($riderA);
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $deliveryA->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

        // Rider A : 403 sur la livraison du rider B (ownership cross-employé).
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $deliveryB->id,
            'type' => 'picked_up',
        ])->assertStatus(403);

        // Rider A : 403 sur une livraison non planifiée.
        $free = $this->createDelivery();
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $free->id,
            'type' => 'picked_up',
        ])->assertStatus(403);
    }

    public function test_rider_cannot_read_colleague_route_via_policy(): void
    {
        $riderA = $this->employee();
        $riderB = $this->employee();

        $routeA = $this->createRoute($riderA);
        $routeB = $this->createRoute($riderB);

        self::assertTrue(Gate::forUser($riderA)->allows('view', $routeA));
        self::assertFalse(Gate::forUser($riderA)->allows('view', $routeB));
        self::assertFalse(Gate::forUser($riderB)->allows('view', $routeA));

        // Un manager consulte toutes les tournées du tenant.
        self::assertTrue(Gate::forUser($this->manager('rh'))->allows('view', $routeB));
    }

    // ── Dispatcher / manager ───────────────────────────────────────────────

    public function test_dispatcher_can_manage_deliveries_and_routes(): void
    {
        $dispatcher = $this->manager('manager');
        Sanctum::actingAs($dispatcher);

        $created = $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(201)->json('data');

        $this->getJson('/api/v1/delivery/deliveries/'.$created['id'])->assertOk();

        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => now()->toDateString(),
            'delivery_ids' => [$created['id']],
        ])->assertStatus(201);
    }

    public function test_hr_manager_can_read_reports_but_not_dispatch(): void
    {
        $hr = $this->manager('rh');
        Sanctum::actingAs($hr);

        // Rapports : autorisés.
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertOk();

        // Écriture dispatcher : refusée (deny-by-default).
        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(403)->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);

        $this->postJson('/api/v1/delivery/deliveries/routes', [])
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
    }

    public function test_admin_has_full_access(): void
    {
        Sanctum::actingAs($this->manager('principal'));

        $this->getJson('/api/v1/delivery/deliveries')->assertOk();
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertOk();
        $this->postJson('/api/v1/delivery/deliveries/routes', [])
            ->assertStatus(422); // validation (payload vide) — la garde RBAC est passée
    }

    // ── Cross-tenant ───────────────────────────────────────────────────────

    public function test_foreign_tenant_delivery_is_404_for_dispatcher(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        $foreignDelivery = Delivery::query()->create([
            'company_id' => $other->id,
            'reference' => 'DLV-2026-999999',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'created',
            'dropoff_contact' => 'Client B',
            'dropoff_address' => 'Casablanca',
        ]);

        Sanctum::actingAs($this->manager('manager'));

        $this->getJson('/api/v1/delivery/deliveries/'.$foreignDelivery->id)->assertStatus(404);

        // La Policy refuse aussi au niveau autorisation (zéro fuite).
        self::assertFalse(Gate::forUser($this->manager())->allows('view', $foreignDelivery));
    }
}

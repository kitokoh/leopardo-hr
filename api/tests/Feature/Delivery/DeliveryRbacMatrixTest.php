<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Support\DeliveryRoleResolver;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D05 (#6294) — Matrice RBAC livreur/dispatcher/manager/admin.
 *
 * - résolveur : principal → admin+dispatcher+manager+reports ; manager simple
 *   → manager+reports ; employé → rider ;
 * - garde `delivery.permission` : 403 DELIVERY_ROLE_REQUIRED sur les
 *   endpoints hors rôle (création de tournée par un rider, rapports par un
 *   rider, événements par n'importe quel employé authentifié OK) ;
 * - la création de tournée reste 403 pour le manager non-dispatcher.
 */
class DeliveryRbacMatrixTest extends TestCase
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

    private function employee(string $role, ?string $managerRole = null): Employee
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

    public function test_role_resolver_matrix(): void
    {
        $resolver = new DeliveryRoleResolver();

        $admin = $this->employee('manager', 'principal');
        self::assertContains('admin', $resolver->rolesFor($admin));
        self::assertContains('dispatcher', $resolver->rolesFor($admin));
        self::assertContains('manager', $resolver->rolesFor($admin));
        self::assertContains('reports', $resolver->rolesFor($admin));

        $manager = $this->employee('manager', 'rh');
        self::assertNotContains('admin', $resolver->rolesFor($manager));
        self::assertNotContains('dispatcher', $resolver->rolesFor($manager));
        self::assertContains('manager', $resolver->rolesFor($manager));
        self::assertContains('reports', $resolver->rolesFor($manager));

        $rider = $this->employee('employee');
        self::assertSame(['rider'], $resolver->rolesFor($rider));
    }

    public function test_dispatcher_can_plan_routes_manager_cannot(): void
    {
        // Un manager simple (non dispatcher) ne peut PAS créer de tournée.
        Sanctum::actingAs($this->employee('manager', 'rh'));
        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [1],
        ])->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);

        // Le dispatcher (principal) le peut.
        Sanctum::actingAs($this->employee('manager', 'principal'));
        $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => '2026-09-01',
            'delivery_ids' => [1],
        ])->assertStatus(422); // validation d'abord (colis inexistant) — la garde est passée
    }

    public function test_reports_are_denied_for_rider(): void
    {
        Sanctum::actingAs($this->employee('employee'));

        $this->getJson('/api/v1/delivery/deliveries/reports/summary')
            ->assertStatus(403)
            ->assertJson(['error' => 'DELIVERY_ROLE_REQUIRED']);
    }

    public function test_rider_can_record_events(): void
    {
        Sanctum::actingAs($this->employee('manager', 'principal'));
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-777001',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'assigned',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ]);

        // Le rider (employé non-manager) enregistre un événement → OK.
        Sanctum::actingAs($this->employee('employee'));
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

        // Mais il ne peut ni créer une livraison, ni consulter les rapports.
        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'X',
            'dropoff_address' => 'Y',
        ])->assertStatus(403);
    }

    public function test_unauthenticated_is_401_before_rbac(): void
    {
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertStatus(401);
    }
}

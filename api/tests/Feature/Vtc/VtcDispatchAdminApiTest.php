<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcDriverPosition;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * VTC-06 (#8362, BC-34 VTC) — console dispatcher + administration + purge
 * RGPD des positions.
 *
 *   - matrice RBAC deny-by-default (dispatcher vs admin vs driver, cf.
 *     docs/architecture/VTC_RBAC.md) ;
 *   - isolation tenant : un dispatcher/admin ne voit que SON tenant ;
 *   - CRUD grilles/véhicules/chauffeurs validés par FormRequests, règles
 *     métier bornées (défaut unique, plaque unique, suppressions gardées) ;
 *   - `vtc:purge-positions` : rétention bornée, idempotente.
 */
class VtcDispatchAdminApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    // ─── Matrice RBAC ────────────────────────────────────────────────

    public function test_dispatch_endpoints_require_dispatcher_role(): void
    {
        $company = $this->createCompany(enableVtc: true);

        // Chauffeur (employé actif) : pas dispatcher → 403.
        $this->actingAs($company, 'employee');

        $this->getJson('/api/v1/vtc/dispatch/rides')
            ->assertStatus(403)
            ->assertJson(['error' => 'VTC_ROLE_REQUIRED']);

        // Manager principal : dispatcher → 200.
        $this->actingAs($company, 'manager', 'principal');

        $this->getJson('/api/v1/vtc/dispatch/rides')->assertOk();
        $this->getJson('/api/v1/vtc/dispatch/drivers')->assertOk();
    }

    public function test_admin_endpoints_require_admin_role(): void
    {
        $company = $this->createCompany(enableVtc: true);

        // Manager non principal : dispatcher mais PAS admin → 403.
        $this->actingAs($company, 'manager', 'manager');

        $this->getJson('/api/v1/vtc/fare-profiles')
            ->assertStatus(403)
            ->assertJson(['error' => 'VTC_ROLE_REQUIRED']);

        // Manager principal : admin → 200.
        $this->actingAs($company, 'manager', 'principal');

        $this->getJson('/api/v1/vtc/fare-profiles')->assertOk();
        $this->getJson('/api/v1/vtc/vehicles')->assertOk();
        $this->getJson('/api/v1/vtc/drivers')->assertOk();
    }

    // ─── Isolation tenant (dispatcher) ───────────────────────────────

    public function test_dispatcher_sees_only_his_tenant(): void
    {
        $companyA = $this->createCompany(enableVtc: true);
        $companyB = $this->createCompany(enableVtc: true);

        $this->withTenantContext($companyA, function (): void {
            VtcRide::factory()->create(['status' => VtcRideStatus::Dispatching->value, 'passenger_name' => 'Course A']);
            VtcDriver::factory()->availableAt(4.0511, 9.7679)->create(['name' => 'Chauffeur A']);
        });

        $this->withTenantContext($companyB, function (): void {
            VtcRide::factory()->create(['status' => VtcRideStatus::Dispatching->value, 'passenger_name' => 'Course B']);
            VtcDriver::factory()->availableAt(4.0511, 9.7679)->create(['name' => 'Chauffeur B']);
        });

        $this->actingAs($companyA, 'manager', 'manager');

        // Courses : seules les ACTIVES du tenant A (course B jamais visible).
        $rides = $this->getJson('/api/v1/vtc/dispatch/rides')->assertOk()->json('data');
        self::assertCount(1, $rides);
        self::assertSame('dispatching', $rides[0]['status']);

        $drivers = $this->getJson('/api/v1/vtc/dispatch/drivers')->assertOk()->json('data');
        self::assertSame(['Chauffeur A'], array_column($drivers, 'name'));
    }

    // ─── CRUD grilles tarifaires ─────────────────────────────────────

    public function test_fare_profiles_crud_and_single_default(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAs($company, 'manager', 'principal');

        $payload = [
            'name' => 'Standard',
            'currency' => 'xaf',
            'base_minor' => 500_00,
            'per_km_minor' => 250_00,
            'per_minute_minor' => 50_00,
            'minimum_minor' => 1_000_00,
            'is_default' => true,
        ];

        $first = $this->postJson('/api/v1/vtc/fare-profiles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.is_default', true);

        // Validation stricte.
        $this->postJson('/api/v1/vtc/fare-profiles', ['name' => 'Incomplete'])
            ->assertStatus(422);

        // Seconde grille par défaut : la première perd le drapeau.
        $second = $this->postJson('/api/v1/vtc/fare-profiles', array_merge($payload, ['name' => 'Premium', 'is_default' => true]))
            ->assertCreated();

        /** @var VtcFareProfile $firstProfile */
        $firstProfile = VtcFareProfile::query()->findOrFail($first->json('data.id'));
        self::assertFalse($firstProfile->is_default);

        // Mise à jour.
        $this->putJson('/api/v1/vtc/fare-profiles/'.$second->json('data.id'), array_merge($payload, [
            'name' => 'Premium Plus',
            'per_km_minor' => 300_00,
        ]))->assertOk()
            ->assertJsonPath('data.name', 'Premium Plus')
            ->assertJsonPath('data.per_km_minor', 300_00);

        // Suppression libre (aucune course ne la référence).
        $this->deleteJson('/api/v1/vtc/fare-profiles/'.$second->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        // Grille référencée par une course : 409.
        /** @var VtcFareProfile $used */
        $used = VtcFareProfile::query()->findOrFail($first->json('data.id'));
        $this->withTenantContext($company, function () use ($used): void {
            VtcRide::factory()->create(['fare_profile_id' => $used->id]);
        });

        $this->deleteJson('/api/v1/vtc/fare-profiles/'.$used->id)
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_FARE_PROFILE_IN_USE']);
    }

    // ─── CRUD véhicules ──────────────────────────────────────────────

    public function test_vehicles_crud_plate_unique_and_assignment_guard(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAs($company, 'manager', 'principal');

        $vehicle = $this->postJson('/api/v1/vtc/vehicles', [
            'plate' => 'lt-001-aa',
            'brand' => 'Toyota',
            'category' => 'berline',
        ])->assertCreated()
            ->assertJsonPath('data.plate', 'LT-001-AA')
            ->assertJsonPath('data.category', 'berline');

        // Plaque dupliquée (même casse différente) : 422 propre.
        $this->postJson('/api/v1/vtc/vehicles', ['plate' => 'LT-001-AA'])
            ->assertStatus(422)
            ->assertJson(['error' => 'VTC_VEHICLE_PLATE_TAKEN']);

        // Catégorie invalide : 422 validation.
        $this->postJson('/api/v1/vtc/vehicles', ['plate' => 'XX-000-XX', 'category' => 'avion'])
            ->assertStatus(422);

        // Véhicule affecté à un chauffeur : suppression refusée (409).
        $this->withTenantContext($company, function () use ($vehicle): void {
            VtcDriver::factory()->create(['vehicle_id' => $vehicle->json('data.id')]);
        });

        $this->deleteJson('/api/v1/vtc/vehicles/'.$vehicle->json('data.id'))
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_VEHICLE_ASSIGNED']);
    }

    // ─── CRUD chauffeurs ─────────────────────────────────────────────

    public function test_drivers_crud_references_and_history_guard(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAs($company, 'manager', 'principal');

        /** @var Employee $linked */
        $linked = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        $driver = $this->postJson('/api/v1/vtc/drivers', [
            'name' => 'Mbappé Chauffeur',
            'phone' => '+237690000000',
            'user_id' => $linked->getAuthIdentifier(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'offline');

        // Références hors tenant : 422 propre.
        $this->postJson('/api/v1/vtc/drivers', ['name' => 'X', 'user_id' => 999_999])
            ->assertStatus(422);
        $this->postJson('/api/v1/vtc/drivers', ['name' => 'X', 'vehicle_id' => 999_999])
            ->assertStatus(422);

        // Mise à jour (passage suspended par l'exploitant).
        $this->putJson('/api/v1/vtc/drivers/'.$driver->json('data.id'), [
            'name' => 'Mbappé Chauffeur',
            'status' => 'suspended',
        ])->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        // Chauffeur avec courses : suppression refusée (409).
        $this->withTenantContext($company, function () use ($driver): void {
            VtcRide::factory()->create(['driver_id' => $driver->json('data.id')]);
        });

        $this->deleteJson('/api/v1/vtc/drivers/'.$driver->json('data.id'))
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_DRIVER_HAS_RIDES']);
    }

    // ─── Purge RGPD ──────────────────────────────────────────────────

    public function test_purge_positions_respects_retention_and_is_idempotent(): void
    {
        $company = $this->createCompany(enableVtc: true);

        /** @var VtcDriver $driver */
        $driver = $this->withTenantContext($company, fn (): VtcDriver => VtcDriver::factory()->availableAt(4.0511, 9.7679)->create());

        $this->withTenantContext($company, function () use ($driver): void {
            // Position de 40 jours : au-delà de la rétention (30 j).
            VtcDriverPosition::create([
                'driver_id' => $driver->id,
                'latitude' => 4.0511,
                'longitude' => 9.7679,
                'recorded_at' => now()->subDays(40),
                'source' => 'app',
            ]);
            // Position de 10 jours : conservée.
            VtcDriverPosition::create([
                'driver_id' => $driver->id,
                'latitude' => 4.0520,
                'longitude' => 9.7680,
                'recorded_at' => now()->subDays(10),
                'source' => 'app',
            ]);
        });

        Artisan::call('vtc:purge-positions');

        $remaining = VtcDriverPosition::query()->withoutGlobalScopes()->count();
        self::assertSame(1, $remaining);

        /** @var VtcDriverPosition|null $kept */
        $kept = VtcDriverPosition::query()->withoutGlobalScopes()->first();
        self::assertTrue($kept->recorded_at->greaterThan(now()->subDays(30)));

        // Idempotente : seconde exécution sans nouvelle suppression.
        Artisan::call('vtc:purge-positions');

        self::assertSame(1, VtcDriverPosition::query()->withoutGlobalScopes()->count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createCompany(bool $enableVtc = false): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableVtc) {
            $company->setFeature('vtc', true);
            $company->save();
        }

        return $company;
    }

    private function actingAs(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_filter([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ], static fn ($value): bool => $value !== null));

        Sanctum::actingAs($employee);

        return $employee;
    }
}

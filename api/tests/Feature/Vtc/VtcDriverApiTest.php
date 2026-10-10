<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Application\Services\VtcDispatchService;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcDriverPosition;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * VTC-05 (#8361, BC-34 VTC) — API chauffeur : offres, transitions de
 * course, positions idempotentes, disponibilité.
 *
 *   - rôle vtc.driver exigé partout (403 deny-by-default sinon) ;
 *   - un chauffeur ne voit que SES offres/courses (404 uniforme) ;
 *   - double acceptation impossible (verrou VTC-04) ;
 *   - positions idempotentes par (driver_id, recorded_at) ;
 *   - golden journey : offre → accept → arrive → start → complete.
 */
class VtcDriverApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Le listener VtcRideRequested (VTC-04) enfile le job de dispatch —
        // faked : les offres sont déclenchées manuellement dans les tests.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_driver_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/vtc/driver/offers')->assertStatus(401);
        $this->postJson('/api/v1/vtc/driver/position', [])->assertStatus(401);
        $this->postJson('/api/v1/vtc/driver/availability', [])->assertStatus(401);
    }

    public function test_driver_endpoints_require_driver_role(): void
    {
        $company = $this->createCompany(enableVtc: true);

        // Manager principal : pas un chauffeur → deny-by-default.
        $this->actingAsEmployee($company, role: 'manager', managerRole: 'principal');

        $this->getJson('/api/v1/vtc/driver/offers')
            ->assertStatus(403)
            ->assertJson(['error' => 'VTC_ROLE_REQUIRED']);
    }

    public function test_driver_endpoints_require_a_linked_driver_profile(): void
    {
        $company = $this->createCompany(enableVtc: true);

        // Employé actif SANS fiche chauffeur rattachée.
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/vtc/driver/offers')
            ->assertStatus(403)
            ->assertJson(['error' => 'VTC_DRIVER_NOT_LINKED']);
    }

    public function test_driver_sees_only_his_pending_offers(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);
        [, $otherDriver] = $this->createLinkedDriver($company, latitude: 4.0611, longitude: 9.7779);

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);

        $this->getJson('/api/v1/vtc/driver/offers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ride_id', $ride->id)
            ->assertJsonPath('data.0.offer_seq', 1);

        // L'autre chauffeur n'a aucune offre.
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::query()->findOrFail($otherDriver->user_id);
        Sanctum::actingAs($otherEmployee);

        $this->getJson('/api/v1/vtc/driver/offers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_accept_and_double_acceptance_is_impossible(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);
        [, $otherDriver] = $this->createLinkedDriver($company, latitude: 4.0611, longitude: 9.7779);

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.driver_id', $driver->id);

        self::assertSame(VtcDriverStatus::Busy, $driver->refresh()->status);

        // Seconde acceptation (même chauffeur) : 409 — verrou VTC-04.
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')
            ->assertStatus(409);

        // Acceptation par un chauffeur jamais sollicité : 409.
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::query()->findOrFail($otherDriver->user_id);
        Sanctum::actingAs($otherEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')
            ->assertStatus(409);
    }

    public function test_decline_cascades_to_the_next_driver(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);
        [, $nextDriver] = $this->createLinkedDriver($company, latitude: 4.0611, longitude: 9.7779);

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatching');

        // L'offre courante est passée au chauffeur suivant.
        self::assertSame(
            $nextDriver->id,
            $ride->refresh()->metadata['pending_offer']['driver_id'] ?? null
        );
    }

    public function test_golden_journey_offer_to_completion(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);

        $this->withTenantContext($company, function (): void {
            VtcFareProfile::factory()->create([
                'base_minor' => 500_00,
                'per_km_minor' => 250_00,
                'per_minute_minor' => 50_00,
                'minimum_minor' => 1_000_00,
                'is_default' => true,
            ]);
        });

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);

        // Temps figé : les positions ingérées doivent tomber DANS la fenêtre
        // [started_at, completed_at] pour le recalcul du prix final.
        $t0 = Carbon::now()->startOfSecond();
        Carbon::setTestNow($t0);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')->assertOk();
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/arrive')
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived');
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        // Deux positions ingérées pendant la course (trajet réel).
        Carbon::setTestNow($t0->copy()->addSeconds(60));
        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0530,
            'lng' => 9.7695,
        ])->assertCreated();

        Carbon::setTestNow($t0->copy()->addSeconds(300));
        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0610,
            'lng' => 9.7775,
        ])->assertCreated();

        $response = $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        Carbon::setTestNow();

        // Prix final recalculé (trajet réel) ≥ minimum de la grille.
        self::assertGreaterThanOrEqual(1_000_00, (int) $response->json('data.final_price_minor'));

        // Le chauffeur est libéré → available.
        self::assertSame(VtcDriverStatus::Available, $driver->refresh()->status);

        // Journal complet du cycle de vie (pluck applique le cast enum
        // Laravel 12 → mapping explicite des valeurs).
        $types = $ride->refresh()->events()->orderBy('id')->pluck('type')
            ->map(static fn (VtcRideEventType $type): string => $type->value)->all();
        self::assertContains('ride.accepted', $types);
        self::assertContains('ride.driver_arrived', $types);
        self::assertContains('ride.started', $types);
        self::assertContains('ride.completed', $types);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);

        // Arriver avant d'accepter : la course n'est pas au chauffeur → 404.
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/arrive')
            ->assertStatus(404);

        // Démarrer sans être passé par arrived : 409 après acceptation.
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')->assertOk();

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/start')
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_INVALID_RIDE_TRANSITION']);

        // Clôturer une course non démarrée : 409.
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/complete')
            ->assertStatus(409);
    }

    public function test_driver_cannot_touch_another_drivers_ride(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);
        [$otherEmployee] = $this->createLinkedDriver($company, latitude: 4.0611, longitude: 9.7779);

        $ride = $this->createRideWithOfferFor($company, $driver);

        Sanctum::actingAs($driverEmployee);
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')->assertOk();

        // Course acceptée par un AUTRE chauffeur : 404 uniforme.
        Sanctum::actingAs($otherEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/arrive')->assertStatus(404);
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/complete')->assertStatus(404);
    }

    public function test_positions_are_idempotent_and_monotonic(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);

        Sanctum::actingAs($driverEmployee);

        $recordedAt = Carbon::now()->startOfSecond()->toIso8601String();

        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0511,
            'lng' => 9.7679,
            'recorded_at' => $recordedAt,
        ])->assertCreated()
            ->assertJsonPath('data.idempotent_replay', false);

        // Rejeu exact (même recorded_at) : 200, aucune ligne en plus.
        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0511,
            'lng' => 9.7679,
            'recorded_at' => $recordedAt,
        ])->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        self::assertSame(1, VtcDriverPosition::query()->count());

        // Position plus récente : la dernière position connue avance.
        $later = Carbon::now()->addMinutes(2)->startOfSecond();
        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0611,
            'lng' => 9.7779,
            'recorded_at' => $later->toIso8601String(),
        ])->assertCreated();

        $fresh = $driver->refresh();
        self::assertSame(4.0611, $fresh->current_latitude);

        // Position hors-ordre (plus ancienne) : historisée, mais la dernière
        // position connue NE régresse PAS.
        $this->postJson('/api/v1/vtc/driver/position', [
            'lat' => 4.0000,
            'lng' => 9.7000,
            'recorded_at' => Carbon::now()->subMinute()->toIso8601String(),
        ])->assertCreated();

        self::assertSame(3, VtcDriverPosition::query()->count());
        self::assertSame(4.0611, $driver->refresh()->current_latitude);
    }

    public function test_availability_toggle_and_locked_states(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company, status: VtcDriverStatus::Offline);

        Sanctum::actingAs($driverEmployee);

        // offline → available.
        $this->postJson('/api/v1/vtc/driver/availability', ['available' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'available');

        // available → offline.
        $this->postJson('/api/v1/vtc/driver/availability', ['available' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'offline');

        // busy : bascule verrouillée (course en cours).
        $this->withTenantContext($company, function () use ($driver): void {
            $driver->forceFill(['status' => VtcDriverStatus::Busy->value])->save();
        });

        $this->postJson('/api/v1/vtc/driver/availability', ['available' => false])
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_AVAILABILITY_LOCKED']);
    }

    public function test_driver_of_another_tenant_cannot_accept_ride(): void
    {
        $companyA = $this->createCompany(enableVtc: true);
        $companyB = $this->createCompany(enableVtc: true);
        [, $driverA] = $this->createLinkedDriver($companyA);
        [$employeeB] = $this->createLinkedDriver($companyB);

        $ride = $this->createRideWithOfferFor($companyA, $driverA);

        Sanctum::actingAs($employeeB);

        // Course d'un autre tenant : 404 (scope tenant) — jamais 409/403.
        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/accept')->assertStatus(404);
    }

    /**
     * Crée un employé + sa fiche chauffeur rattachée (user_id).
     *
     * @return array{0: Employee, 1: VtcDriver}
     */
    private function createLinkedDriver(
        Company $company,
        float $latitude = 4.0515,
        float $longitude = 9.7682,
        VtcDriverStatus $status = VtcDriverStatus::Available,
    ): array {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        /** @var VtcDriver $driver */
        $driver = $this->withTenantContext($company, fn (): VtcDriver => VtcDriver::factory()->create([
            'user_id' => $employee->getAuthIdentifier(),
            'status' => $status->value,
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'location_updated_at' => now(),
        ]));

        return [$employee, $driver];
    }

    /**
     * Course `dispatching` dont l'offre courante appartient au chauffeur
     * (dispatch réel exécuté via le service — file faked en setUp).
     */
    private function createRideWithOfferFor(Company $company, VtcDriver $driver): VtcRide
    {
        return $this->withTenantContext($company, function (): VtcRide {
            /** @var VtcRide $ride */
            $ride = VtcRide::factory()->create([
                'status' => VtcRideStatus::Dispatching->value,
                'pickup_latitude' => 4.0511,
                'pickup_longitude' => 9.7679,
                'requested_at' => now(),
            ]);

            /** @var VtcDispatchService $dispatch */
            $dispatch = $this->app->make(VtcDispatchService::class);
            $dispatch->offerToNextCandidate($ride);

            return $ride->refresh();
        });
    }

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

    private function actingAsEmployee(Company $company, string $role = 'employee', ?string $managerRole = null): Employee
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

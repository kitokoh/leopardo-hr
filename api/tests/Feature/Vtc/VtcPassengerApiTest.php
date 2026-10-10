<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideRequested;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * VTC-03 (#8359, BC-34 VTC) — API passager : devis, création idempotente,
 * consultation, annulation.
 *
 *   - 401 sans authentification, 403 flag `vtc` inactif ;
 *   - devis cohérent avec la grille tarifaire (distance via core geo) ;
 *   - création 201 + devis persisté + journal + événement de domaine ;
 *   - double POST même Idempotency-Key → 200, UNE seule course ;
 *   - visibilité : sa course uniquement (404 uniforme sinon) ;
 *   - annulation avant acceptation avec motif tracé ; 409 ensuite ;
 *   - isolation tenant croisée.
 */
class VtcPassengerApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Le listener VtcRideRequested (VTC-04) enfile le job de dispatch —
        // faked ici : ces tests couvrent la surface passager, pas le moteur
        // (sync driver exécuterait le job immédiatement et expirerait les
        // courses sans chauffeur seedé).
        Queue::fake();
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/vtc/rides/estimate', [])->assertStatus(401);
        $this->postJson('/api/v1/vtc/rides', [])->assertStatus(401);
        $this->getJson('/api/v1/vtc/rides/1')->assertStatus(401);
    }

    public function test_endpoints_are_rejected_when_feature_flag_disabled(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/vtc/rides/estimate', $this->estimatePayload())
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_estimate_returns_coherent_fare_from_default_profile(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $this->withTenantContext($company, function (): void {
            VtcFareProfile::factory()->create([
                'base_minor' => 500_00,
                'per_km_minor' => 250_00,
                'per_minute_minor' => 50_00,
                'minimum_minor' => 1_000_00,
                'is_default' => true,
            ]);
        });

        $response = $this->postJson('/api/v1/vtc/rides/estimate', $this->estimatePayload())
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['distance_m', 'road_distance_m', 'duration_s', 'price_minor', 'currency', 'fare_profile_id'],
            ]);

        // Douala centre → ~1.57 km à vol d'oiseau (±3 % moteur), ×1.3 routier.
        self::assertEqualsWithDelta(1_570, (int) $response->json('data.distance_m'), 60.0);
        self::assertEqualsWithDelta(2_040, (int) $response->json('data.road_distance_m'), 80.0);
        // 500 + 250×2.04 + 50×5.6 ≈ 1 289 (borne large, formule exacte en Unit).
        $price = (int) $response->json('data.price_minor');
        self::assertGreaterThan(1_200_00, $price);
        self::assertLessThan(1_400_00, $price);
        self::assertSame('XAF', $response->json('data.currency'));
    }

    public function test_estimate_validates_coordinates(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/vtc/rides/estimate', [])->assertStatus(422);

        $this->postJson('/api/v1/vtc/rides/estimate', [
            'pickup' => ['lat' => 91.0, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
        ])->assertStatus(422);
    }

    public function test_store_creates_dispatching_ride_with_estimate_journal_and_event(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $employee = $this->actingAsEmployee($company);

        Event::fake([VtcRideRequested::class]);

        $key = (string) Str::uuid();

        $response = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload() + [
                'passenger_name' => 'Awa Ngo',
                'pickup_address' => 'Akwa, Douala',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'dispatching')
            ->assertJsonPath('data.passenger_name', 'Awa Ngo');

        $reference = $response->json('data.reference');
        self::assertMatchesRegularExpression('/^VTC-\d{4}-\d{6}$/', (string) $reference);

        /** @var VtcRide $ride */
        $ride = VtcRide::query()->firstOrFail();

        self::assertSame($employee->getAuthIdentifier(), $ride->passenger_user_id);
        self::assertSame(VtcRideStatus::Dispatching, $ride->status);
        self::assertNotNull($ride->requested_at);
        self::assertSame($key, $ride->idempotency_key);

        // Journal append-only : demande + démarrage du dispatch (pluck
        // applique le cast enum Laravel 12 → mapping explicite des valeurs).
        self::assertSame(
            ['ride.requested', 'dispatch.started'],
            $ride->events()->orderBy('id')->pluck('type')
                ->map(static fn (VtcRideEventType $type): string => $type->value)->all()
        );

        Event::assertDispatched(VtcRideRequested::class, static fn (VtcRideRequested $event): bool => $event->rideId === $ride->id);
    }

    public function test_store_is_idempotent_with_same_key(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $key = (string) Str::uuid();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertOk();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, VtcRide::query()->count());
    }

    public function test_store_requires_idempotency_key(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertStatus(422);

        $this->withHeader('Idempotency-Key', 'not-a-uuid')
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertStatus(422);
    }

    public function test_show_is_scoped_to_own_rides(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $rideId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/v1/vtc/rides/'.$rideId)->assertOk();

        // Autre employé du même tenant (non manager) : 404 uniforme.
        /** @var Employee $other */
        $other = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/vtc/rides/'.$rideId)->assertStatus(404);

        // Manager du tenant : visibilité ops conservée.
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/vtc/rides/'.$rideId)->assertOk();
    }

    public function test_cancel_before_acceptance_with_traced_reason(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $this->actingAsEmployee($company);

        $rideId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/vtc/rides', $this->estimatePayload())
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/vtc/rides/'.$rideId.'/cancel', ['reason' => 'Changement de programme'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Changement de programme');

        /** @var VtcRide $ride */
        $ride = VtcRide::query()->findOrFail($rideId);
        self::assertSame(VtcRideStatus::Cancelled, $ride->status);
        self::assertNotNull($ride->cancelled_at);

        // Motif requis.
        $this->postJson('/api/v1/vtc/rides/'.$rideId.'/cancel', [])->assertStatus(422);

        // Seconde annulation : 409 (état terminal).
        $this->postJson('/api/v1/vtc/rides/'.$rideId.'/cancel', ['reason' => 'Encore'])
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_INVALID_RIDE_TRANSITION']);
    }

    public function test_cancel_after_acceptance_is_rejected(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $employee = $this->actingAsEmployee($company);

        /** @var VtcRide $ride */
        $ride = $this->withTenantContext($company, fn (): VtcRide => VtcRide::factory()->create([
            'passenger_user_id' => $employee->getAuthIdentifier(),
            'status' => VtcRideStatus::Accepted->value,
            'accepted_at' => now(),
        ]));

        $this->postJson('/api/v1/vtc/rides/'.$ride->id.'/cancel', ['reason' => 'Trop tard'])
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_INVALID_RIDE_TRANSITION']);
    }

    public function test_rides_never_leak_across_tenants(): void
    {
        $companyA = $this->createCompany(enableVtc: true);
        $companyB = $this->createCompany(enableVtc: true);

        /** @var VtcRide $rideB */
        $rideB = $this->withTenantContext($companyB, fn (): VtcRide => VtcRide::factory()->create());

        $this->actingAsEmployee($companyA);

        $this->getJson('/api/v1/vtc/rides/'.$rideB->id)->assertStatus(404);
        $this->postJson('/api/v1/vtc/rides/'.$rideB->id.'/cancel', ['reason' => 'Intrusion'])
            ->assertStatus(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function estimatePayload(): array
    {
        // Douala centre → ~1.57 km à vol d'oiseau.
        return [
            'pickup' => ['lat' => 4.0511, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
        ];
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

    private function actingAsEmployee(Company $company, string $role = 'employee'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }
}

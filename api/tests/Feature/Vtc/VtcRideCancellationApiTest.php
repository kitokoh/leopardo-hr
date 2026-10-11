<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideCancelled;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * #8423 (M-21) — Annulation exposée aux acteurs ops : chauffeur,
 * dispatcher, admin (extension de CancelRideAction, BC-35 VTC).
 *
 *   - chauffeur : SA course, après acceptation (accepted/arrived) — motif
 *     obligatoire, libération (→ available), journal + événement tracés ;
 *     409 avant acceptation… la course n'est pas encore la sienne (404) ;
 *     409 sur in_progress (state machine), 404 sur la course d'un autre ;
 *   - dispatcher/admin : tout état non terminal depuis la console —
 *     l'acteur réel est tracé (`admin` pour un manager principal,
 *     `dispatcher` sinon) ; un chauffeur assigné est libéré ;
 *   - deny-by-default : employé sans rôle ops → 403 ; non authentifié →
 *     401 ; motif manquant → 422 ; état terminal → 409.
 */
class VtcRideCancellationApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun job de dispatch ne doit partir depuis ces tests (les
        // fixtures posent les courses directement en base).
        Queue::fake();
    }

    // ── Chauffeur ───────────────────────────────────────────────────────

    public function test_driver_cancels_his_accepted_ride_and_is_freed(): void
    {
        Event::fake([VtcRideCancelled::class]);

        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company, status: VtcDriverStatus::Busy);
        $ride = $this->createRide($company, [
            'status' => VtcRideStatus::Accepted->value,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/cancel', [
            'reason' => 'Panne du véhicule',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VtcRideStatus::Cancelled->value);

        $ride->refresh();
        self::assertSame(VtcRideStatus::Cancelled, $ride->status);
        self::assertSame('Panne du véhicule', $ride->cancel_reason);
        self::assertNotNull($ride->cancelled_at);

        // Le chauffeur est libéré — jamais `busy` sur une course morte.
        self::assertSame(VtcDriverStatus::Available, $driver->refresh()->status);

        // Journal append-only : acteur tracé.
        /** @var VtcRideEvent $journalEntry */
        $journalEntry = VtcRideEvent::query()
            ->where('ride_id', $ride->id)
            ->where('type', VtcRideEventType::RideCancelled->value)
            ->firstOrFail();
        self::assertSame('driver', $journalEntry->payload['cancelled_by']);
        self::assertSame('Panne du véhicule', $journalEntry->payload['reason']);

        Event::assertDispatched(
            VtcRideCancelled::class,
            static fn (VtcRideCancelled $event): bool => $event->rideId === $ride->id,
        );
    }

    public function test_driver_cancels_his_arrived_ride(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company, status: VtcDriverStatus::Busy);
        $ride = $this->createRide($company, [
            'status' => VtcRideStatus::Arrived->value,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/cancel', [
            'reason' => 'Empêchement imprévu',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VtcRideStatus::Cancelled->value);
    }

    public function test_driver_cannot_cancel_a_ride_that_is_not_his(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee] = $this->createLinkedDriver($company);
        [, $otherDriver] = $this->createLinkedDriver($company);

        // Course encore en dispatch : aucun chauffeur assigné → 404.
        $unassigned = $this->createRide($company, [
            'status' => VtcRideStatus::Dispatching->value,
        ]);
        // Course acceptée par un AUTRE chauffeur → 404 uniforme.
        $othersRide = $this->createRide($company, [
            'status' => VtcRideStatus::Accepted->value,
            'driver_id' => $otherDriver->id,
        ]);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$unassigned->id.'/cancel', [
            'reason' => 'Tentative avant acceptation',
        ])->assertStatus(404);

        $this->postJson('/api/v1/vtc/driver/rides/'.$othersRide->id.'/cancel', [
            'reason' => 'Tentative sur autrui',
        ])->assertStatus(404);
    }

    public function test_driver_cannot_cancel_an_in_progress_ride(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company, status: VtcDriverStatus::Busy);
        $ride = $this->createRide($company, [
            'status' => VtcRideStatus::InProgress->value,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($driverEmployee);

        $this->postJson('/api/v1/vtc/driver/rides/'.$ride->id.'/cancel', [
            'reason' => 'Tentative en cours de route',
        ])
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_INVALID_RIDE_TRANSITION']);

        self::assertSame(VtcRideStatus::InProgress, $ride->refresh()->status);
    }

    // ── Dispatcher / admin ──────────────────────────────────────────────

    public function test_dispatcher_cancels_any_active_ride_and_frees_the_driver(): void
    {
        $company = $this->createCompany(enableVtc: true);
        [, $driver] = $this->createLinkedDriver($company, status: VtcDriverStatus::Busy);

        $dispatching = $this->createRide($company, [
            'status' => VtcRideStatus::Dispatching->value,
        ]);
        $accepted = $this->createRide($company, [
            'status' => VtcRideStatus::Accepted->value,
            'driver_id' => $driver->id,
        ]);

        $this->actingAsEmployee($company, role: 'manager', managerRole: 'manager');

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$dispatching->id.'/cancel', [
            'reason' => 'Course bloquée en répartition',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VtcRideStatus::Cancelled->value);

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$accepted->id.'/cancel', [
            'reason' => 'Arbitrage ops',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VtcRideStatus::Cancelled->value);

        // Chauffeur libéré par l'annulation ops.
        self::assertSame(VtcDriverStatus::Available, $driver->refresh()->status);

        /** @var VtcRideEvent $journalEntry */
        $journalEntry = VtcRideEvent::query()
            ->where('ride_id', $accepted->id)
            ->where('type', VtcRideEventType::RideCancelled->value)
            ->firstOrFail();
        self::assertSame('dispatcher', $journalEntry->payload['cancelled_by']);
    }

    public function test_admin_principal_cancel_is_traced_as_admin(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $ride = $this->createRide($company, [
            'status' => VtcRideStatus::Dispatching->value,
        ]);

        $this->actingAsEmployee($company, role: 'manager', managerRole: 'principal');

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$ride->id.'/cancel', [
            'reason' => 'Décision du propriétaire',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VtcRideStatus::Cancelled->value);

        /** @var VtcRideEvent $journalEntry */
        $journalEntry = VtcRideEvent::query()
            ->where('ride_id', $ride->id)
            ->where('type', VtcRideEventType::RideCancelled->value)
            ->firstOrFail();
        self::assertSame('admin', $journalEntry->payload['cancelled_by']);
    }

    // ── Gardes ──────────────────────────────────────────────────────────

    public function test_employee_without_ops_role_is_denied_on_dispatch_cancel(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $ride = $this->createRide($company);

        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$ride->id.'/cancel', [
            'reason' => 'Tentative sans rôle',
        ])
            ->assertStatus(403)
            ->assertJson(['error' => 'VTC_ROLE_REQUIRED']);

        self::assertSame(VtcRideStatus::Dispatching, $ride->refresh()->status);
    }

    public function test_ops_cancel_requires_authentication(): void
    {
        $this->postJson('/api/v1/vtc/dispatch/rides/1/cancel', ['reason' => 'x'])
            ->assertStatus(401);
        $this->postJson('/api/v1/vtc/driver/rides/1/cancel', ['reason' => 'x'])
            ->assertStatus(401);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $ride = $this->createRide($company);

        $this->actingAsEmployee($company, role: 'manager', managerRole: 'manager');

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$ride->id.'/cancel', [])
            ->assertStatus(422);
    }

    public function test_cancelling_a_terminal_ride_returns_409(): void
    {
        $company = $this->createCompany(enableVtc: true);
        $completed = $this->createRide($company, [
            'status' => VtcRideStatus::Completed->value,
        ]);

        $this->actingAsEmployee($company, role: 'manager', managerRole: 'principal');

        $this->postJson('/api/v1/vtc/dispatch/rides/'.$completed->id.'/cancel', [
            'reason' => 'Tentative sur état terminal',
        ])
            ->assertStatus(409)
            ->assertJson(['error' => 'VTC_INVALID_RIDE_TRANSITION']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────

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

    /**
     * @return array{0: Employee, 1: VtcDriver}
     */
    private function createLinkedDriver(
        Company $company,
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
            'current_latitude' => 4.0515,
            'current_longitude' => 9.7682,
            'location_updated_at' => now(),
        ]));

        return [$employee, $driver];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRide(Company $company, array $attributes = []): VtcRide
    {
        return $this->withTenantContext($company, fn (): VtcRide => VtcRide::factory()->create(array_merge([
            'status' => VtcRideStatus::Dispatching->value,
            'requested_at' => now(),
        ], $attributes)));
    }
}

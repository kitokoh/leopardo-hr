<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-004, #7946) — réservations : anti-overbooking
 * transactionnel (comptage par type/intervalle, capacité opérationnelle),
 * machine à états gardée (409 INVALID_RESERVATION_TRANSITION), idempotence
 * de création, expiration automatique des pending en ligne, KPIs.
 */
class HospitalityReservationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $admin;

    private Employee $lambda;

    private Employee $otherAdmin;

    private HospitalityProperty $property;

    private HospitalityRoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        $this->admin = $this->manager($this->company);
        $this->lambda = $this->employee($this->company);
        $this->otherAdmin = $this->manager($this->otherCompany);

        $this->property = $this->createProperty($this->company);
        $this->roomType = $this->createRoomType($this->property, 'STD', 'Standard');
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function createProperty(Company $company): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Hôtel Test',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        return $property;
    }

    private function createRoomType(HospitalityProperty $property, string $code, string $name): HospitalityRoomType
    {
        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'code' => $code,
            'name' => $name,
            'base_price_minor' => 12000,
            'currency' => 'EUR',
        ]);

        return $roomType;
    }

    private function createUnit(HospitalityProperty $property, ?HospitalityRoomType $roomType, string $code, string $status = 'available'): HospitalityUnit
    {
        /** @var HospitalityUnit $unit */
        $unit = HospitalityUnit::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'room_type_id' => $roomType?->getKey(),
            'code' => $code,
            'status' => $status,
        ]);

        return $unit;
    }

    /** @return array<string, mixed> */
    private function reservationPayload(array $overrides = []): array
    {
        return array_merge([
            'property_id' => $this->property->getKey(),
            'room_type_id' => $this->roomType->getKey(),
            'guest_name' => 'Awa Ndiaye',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-04',
            'adults' => 2,
            'total_amount_minor' => 36000,
            'currency' => 'EUR',
        ], $overrides);
    }

    private function makeReservation(array $overrides): HospitalityReservation
    {
        /** @var HospitalityReservation $reservation */
        $reservation = HospitalityReservation::query()->withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->company->id,
            'reference' => 'HRS-'.fake()->unique()->bothify('????##'),
            'property_id' => $this->property->getKey(),
            'room_type_id' => $this->roomType->getKey(),
            'guest_name' => 'Client Test',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-04',
            'adults' => 1,
            'status' => 'confirmed',
            'source' => 'desk',
        ], $overrides));

        return $reservation;
    }

    // ── Gate & RBAC ────────────────────────────────────────────────────

    public function test_reservations_require_authentication(): void
    {
        $this->getJson('/api/v1/hospitality/reservations')->assertStatus(401);
    }

    public function test_reservations_return_403_when_solution_disabled(): void
    {
        /** @var Company $disabled */
        $disabled = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        Sanctum::actingAs($this->manager($disabled));

        $this->getJson('/api/v1/hospitality/reservations')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HOSPITALITY_SOLUTION_INACTIVE');
    }

    public function test_lambda_employee_cannot_create_a_desk_reservation(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        Sanctum::actingAs($this->lambda);

        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload())
            ->assertStatus(403);
    }

    // ── Création & validation ─────────────────────────────────────────

    public function test_admin_can_create_a_desk_reservation(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');

        Sanctum::actingAs($this->admin);

        $data = $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload())
            ->assertStatus(201)
            ->json('data');

        $this->assertStringStartsWith('HRS-', $data['reference']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('desk', $data['source']);
        $this->assertSame(['confirmed', 'cancelled'], $data['allowed_transitions']);
    }

    public function test_check_out_must_be_after_check_in(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'check_in' => '2026-10-04',
            'check_out' => '2026-10-04',
        ]))->assertStatus(422);

        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'check_in' => '2026-10-05',
            'check_out' => '2026-10-04',
        ]))->assertStatus(422);
    }

    public function test_room_type_must_belong_to_the_property(): void
    {
        $otherProperty = $this->createProperty($this->company);
        $foreignType = $this->createRoomType($otherProperty, 'DLX', 'Deluxe');
        $this->createUnit($this->property, $this->roomType, 'CH-1');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'room_type_id' => $foreignType->getKey(),
        ]))->assertStatus(422);
    }

    // ── Anti-overbooking transactionnel ────────────────────────────────

    public function test_overbooking_is_refused_and_cancellation_frees_capacity(): void
    {
        // Capacité = 2 unités opérationnelles du type STD.
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        $this->createUnit($this->property, $this->roomType, 'CH-2');

        Sanctum::actingAs($this->admin);

        // 2 réservations confirmées qui se chevauchent mutuellement →
        // capacité (2) atteinte sur l'intervalle commun.
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'status' => 'confirmed',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
        ]))->assertStatus(201);
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'status' => 'confirmed',
            'check_in' => '2026-10-02',
            'check_out' => '2026-10-04',
        ]))->assertStatus(201);

        // 3e réservation chevauchant LES DEUX → 409 HOSPITALITY_NO_AVAILABILITY.
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'check_in' => '2026-10-03',
            'check_out' => '2026-10-04',
        ]))->assertStatus(409);

        // Intervalle ADJACENT (check_in = check_out de l'existant) : pas de
        // chevauchement [check_in, check_out) → accepté.
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'check_in' => '2026-10-05',
            'check_out' => '2026-10-07',
        ]))->assertStatus(201);

        // Une annulation libère la capacité immédiatement : la réservation
        // refusée ci-dessus ne chevauche plus qu'une seule tenante → 201.
        $first = HospitalityReservation::query()->orderBy('id')->first();
        $this->postJson("/api/v1/hospitality/reservations/{$first->getKey()}/cancel")->assertStatus(200);

        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'check_in' => '2026-10-03',
            'check_out' => '2026-10-04',
        ]))->assertStatus(201);
    }

    public function test_units_under_maintenance_reduce_the_operational_capacity(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        $this->createUnit($this->property, $this->roomType, 'CH-2', 'maintenance');

        Sanctum::actingAs($this->admin);

        // Capacité opérationnelle = 1 → la 2e réservation est refusée.
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload(['status' => 'confirmed']))
            ->assertStatus(201);
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload())
            ->assertStatus(409);
    }

    public function test_expired_pending_does_not_hold_inventory_anymore(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');

        // Pending en ligne EXPIRÉE (créée directement — la commande
        // d'expiration n'est pas encore passée).
        $this->makeReservation([
            'status' => 'pending',
            'source' => 'online',
            'expires_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($this->admin);

        // Elle n'immobilise plus : la capacité reste disponible.
        $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload(['status' => 'confirmed']))
            ->assertStatus(201);
    }

    // ── Disponibilité ──────────────────────────────────────────────────

    public function test_availability_returns_capacity_held_and_available_per_type(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        $this->createUnit($this->property, $this->roomType, 'CH-2');
        $this->createUnit($this->property, $this->roomType, 'CH-3', 'maintenance');
        $this->makeReservation(['status' => 'confirmed']); // 2026-10-01 → 2026-10-04

        Sanctum::actingAs($this->admin);

        $data = $this->getJson("/api/v1/hospitality/properties/{$this->property->getKey()}/availability?from=2026-10-02&to=2026-10-05")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(2, $data['room_types'][0]['capacity']);
        $this->assertSame(1, $data['room_types'][0]['held']);
        $this->assertSame(1, $data['room_types'][0]['available']);
    }

    // ── Machine à états ────────────────────────────────────────────────

    public function test_full_transition_chain_and_unit_status_side_effects(): void
    {
        $unit = $this->createUnit($this->property, $this->roomType, 'CH-1');
        $reservation = $this->makeReservation(['status' => 'pending', 'unit_id' => $unit->getKey()]);

        Sanctum::actingAs($this->admin);

        // pending → confirmed → checked_in → checked_out.
        $this->postJson("/api/v1/hospitality/reservations/{$reservation->getKey()}/confirm")
            ->assertStatus(200)->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/v1/hospitality/reservations/{$reservation->getKey()}/check-in")
            ->assertStatus(200)->assertJsonPath('data.status', 'checked_in');
        $this->assertSame('occupied', $unit->refresh()->status);

        $this->postJson("/api/v1/hospitality/reservations/{$reservation->getKey()}/check-out")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.allowed_transitions', []);
        $this->assertSame('available', $unit->refresh()->status);
    }

    public function test_invalid_transitions_are_a_409(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        $pending = $this->makeReservation(['status' => 'pending']);
        $checkedOut = $this->makeReservation(['status' => 'checked_out', 'check_in' => '2026-11-01', 'check_out' => '2026-11-02']);

        Sanctum::actingAs($this->admin);

        // check-in sur pending → 409 INVALID_RESERVATION_TRANSITION.
        $this->postJson("/api/v1/hospitality/reservations/{$pending->getKey()}/check-in")
            ->assertStatus(409);

        // no-show sur pending → 409.
        $this->postJson("/api/v1/hospitality/reservations/{$pending->getKey()}/no-show")
            ->assertStatus(409);

        // État terminal : toute transition → 409.
        $this->postJson("/api/v1/hospitality/reservations/{$checkedOut->getKey()}/cancel")
            ->assertStatus(409);

        // cancel depuis pending → OK ; no-show depuis confirmed → OK.
        $this->postJson("/api/v1/hospitality/reservations/{$pending->getKey()}/cancel")
            ->assertStatus(200)->assertJsonPath('data.status', 'cancelled');

        $confirmed = $this->makeReservation(['status' => 'confirmed', 'check_in' => '2026-12-01', 'check_out' => '2026-12-02']);
        $this->postJson("/api/v1/hospitality/reservations/{$confirmed->getKey()}/no-show")
            ->assertStatus(200)->assertJsonPath('data.status', 'no_show');
    }

    // ── Mise à jour & idempotence ─────────────────────────────────────

    public function test_update_rechecks_availability_and_terminal_states_are_frozen(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');
        $blocking = $this->makeReservation(['status' => 'confirmed']); // 10-01 → 10-04
        $moving = $this->makeReservation(['status' => 'pending', 'check_in' => '2026-11-10', 'check_out' => '2026-11-12']);

        Sanctum::actingAs($this->admin);

        // Déplacer la réservation pending sur l'intervalle plein → 409.
        $this->patchJson("/api/v1/hospitality/reservations/{$moving->getKey()}", [
            'check_in' => '2026-10-02',
            'check_out' => '2026-10-03',
        ])->assertStatus(409);

        // Déplacement libre → 200 (version incrémentée).
        $this->patchJson("/api/v1/hospitality/reservations/{$moving->getKey()}", [
            'check_in' => '2026-10-04',
            'check_out' => '2026-10-06',
            'guest_name' => 'Moussa Diop',
        ])->assertStatus(200)
            ->assertJsonPath('data.guest_name', 'Moussa Diop')
            ->assertJsonPath('data.version', 1);

        // État terminal : édition refusée (409).
        $cancelled = $this->makeReservation(['status' => 'cancelled', 'check_in' => '2026-12-05', 'check_out' => '2026-12-06']);
        $this->patchJson("/api/v1/hospitality/reservations/{$cancelled->getKey()}", ['guest_name' => 'X'])
            ->assertStatus(409);
    }

    public function test_creation_is_idempotent_on_idempotency_key(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1');

        Sanctum::actingAs($this->admin);

        $first = $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'idempotency_key' => 'desk-abc-123',
        ]))->assertStatus(201)->json('data');

        // Rejeu avec la MÊME clé → 200, même réservation, aucun doublon.
        $replay = $this->postJson('/api/v1/hospitality/reservations', $this->reservationPayload([
            'idempotency_key' => 'desk-abc-123',
        ]))->assertStatus(200)->json('data');

        $this->assertSame($first['id'], $replay['id']);
        $this->assertSame(1, HospitalityReservation::query()->count());
    }

    // ── Expiration automatique ─────────────────────────────────────────

    public function test_expire_pending_reservations_command(): void
    {
        $expiredOnline = $this->makeReservation([
            'status' => 'pending',
            'source' => 'online',
            'expires_at' => now()->subMinutes(2),
        ]);
        $freshOnline = $this->makeReservation([
            'status' => 'pending',
            'source' => 'online',
            'expires_at' => now()->addMinutes(20),
        ]);
        $deskPending = $this->makeReservation(['status' => 'pending']); // sans expiration (guichet)
        $confirmed = $this->makeReservation(['status' => 'confirmed', 'check_in' => '2026-11-01', 'check_out' => '2026-11-02']);

        $this->artisan('hospitality:expire-pending-reservations', ['--company' => $this->company->id])
            ->assertExitCode(0);

        $this->assertSame('cancelled', $expiredOnline->refresh()->status);
        $this->assertSame('pending', $freshOnline->refresh()->status);
        $this->assertSame('pending', $deskPending->refresh()->status);
        $this->assertSame('confirmed', $confirmed->refresh()->status);

        // Idempotent : une seconde passe n'expire plus rien.
        $this->artisan('hospitality:expire-pending-reservations', ['--company' => $this->company->id])
            ->assertExitCode(0);
        $this->assertSame(1, HospitalityReservation::query()->where('status', 'cancelled')->count());
    }

    // ── Cross-tenant ──────────────────────────────────────────────────

    public function test_cross_tenant_reservation_is_a_404(): void
    {
        /** @var HospitalityReservation $foreign */
        $foreign = HospitalityReservation::query()->withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'reference' => 'HRS-FRGN-01',
            'property_id' => $this->createProperty($this->otherCompany)->getKey(),
            'room_type_id' => 1,
            'guest_name' => 'Autre tenant',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-02',
            'status' => 'pending',
            'source' => 'desk',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/hospitality/reservations/{$foreign->getKey()}")->assertStatus(404);
        $this->patchJson("/api/v1/hospitality/reservations/{$foreign->getKey()}", ['guest_name' => 'Hack'])->assertStatus(404);
        $this->postJson("/api/v1/hospitality/reservations/{$foreign->getKey()}/confirm")->assertStatus(404);
    }

    // ── KPIs ───────────────────────────────────────────────────────────

    public function test_dashboard_kpis(): void
    {
        $this->createUnit($this->property, $this->roomType, 'CH-1', 'occupied');
        $this->createUnit($this->property, $this->roomType, 'CH-2', 'available');
        $this->createUnit($this->property, $this->roomType, 'CH-3', 'maintenance');

        $today = now()->toDateString();
        $this->makeReservation(['status' => 'confirmed', 'check_in' => $today, 'check_out' => now()->addDays(2)->toDateString()]);
        $this->makeReservation(['status' => 'checked_in', 'check_in' => now()->subDays(2)->toDateString(), 'check_out' => $today]);
        $this->makeReservation(['status' => 'pending', 'source' => 'online', 'expires_at' => now()->addMinutes(25), 'check_in' => '2026-12-01', 'check_out' => '2026-12-02']);

        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/v1/hospitality/dashboard/kpis')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(2, $data['occupancy']['operational_units']);
        $this->assertSame(1, $data['occupancy']['occupied_units']);
        $this->assertSame(0.5, $data['occupancy']['rate']);
        $this->assertSame(1, $data['arrivals_today']);
        $this->assertSame(1, $data['departures_today']);
        $this->assertSame(1, $data['pending_online']);
    }
}

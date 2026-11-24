<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoomInventory;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * M4 — inventaire hôtelier par date + contrôle de surréservation.
 *
 * Ce que ces tests verrouillent :
 * - une chambre est vendable NUIT PAR NUIT (le catalogue seul ne le permettait pas) ;
 * - deux réservations ne peuvent pas dépasser `rooms_total` sur une même nuit (409) ;
 * - un refus n'écrête AUCUNE nuit (pas de réservation partielle) ;
 * - un rejeu avec la même clé d'idempotence ne consomme pas deux fois l'inventaire ;
 * - une baisse de capacité sous le déjà-vendu est refusée (422) ;
 * - l'isolation multi-tenant tient (404 cross-tenant).
 */
class TravelHotelInventoryTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TravelHotel $hotel;

    private TravelHotelRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $company->setFeature('travelagency', true);
        $company->save();

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($principal);

        [$this->hotel, $this->room] = app(TenantManager::class)->withinTenant($company, function (): array {
            $city = TravelCity::factory()->create();
            $hotel = TravelHotel::factory()->create(['city_id' => $city->id]);
            $room = TravelHotelRoom::factory()->create([
                'hotel_id' => $hotel->id,
                'room_number' => '101',
                'capacity' => 2,
                'price_per_night_minor' => 20000,
                'currency' => 'XAF',
            ]);

            return [$hotel, $room];
        });
    }

    private function inventoryUrl(): string
    {
        return "/api/v1/travel/hotels/{$this->hotel->id}/rooms/{$this->room->id}/inventory";
    }

    private function bookingsUrl(): string
    {
        return "/api/v1/travel/hotels/{$this->hotel->id}/rooms/{$this->room->id}/bookings";
    }

    public function test_inventory_grid_is_continuous_and_defaults_to_one_room(): void
    {
        $from = Carbon::today()->addDays(10)->toDateString();
        $to = Carbon::today()->addDays(12)->toDateString();

        $response = $this->getJson($this->inventoryUrl()."?from={$from}&to={$to}")
            ->assertStatus(200);

        $days = $response->json('data');

        $this->assertCount(3, $days, 'La grille doit couvrir from..to inclus.');
        $this->assertSame($from, $days[0]['stay_date']);
        $this->assertSame(1, $days[0]['rooms_total']);
        $this->assertSame(0, $days[0]['rooms_booked']);
        $this->assertSame(1, $days[0]['rooms_remaining']);
    }

    public function test_principal_can_open_inventory_for_a_date_range(): void
    {
        $from = Carbon::today()->addDays(20)->toDateString();
        $to = Carbon::today()->addDays(22)->toDateString();

        $this->putJson($this->inventoryUrl(), [
            'from' => $from,
            'to' => $to,
            'rooms_total' => 3,
        ])->assertStatus(200)
            ->assertJsonPath('data.0.rooms_total', 3)
            ->assertJsonPath('data.0.rooms_remaining', 3);

        $stored = app(TenantManager::class)->withinTenant($this->company, function () use ($from): int {
            return TravelHotelRoomInventory::query()
                ->where('room_id', $this->room->id)
                ->where('stay_date', $from)
                ->count();
        });

        $this->assertSame(1, $stored, 'Une ligne d\'inventaire doit être persistée par nuit.');
    }

    public function test_booking_consumes_inventory_night_by_night(): void
    {
        $checkIn = Carbon::today()->addDays(30);
        $checkOut = $checkIn->copy()->addDays(2);

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkOut->copy()->subDay()->toDateString(),
            'rooms_total' => 2,
        ])->assertStatus(200);

        $this->postJson($this->bookingsUrl(), [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
            'idempotency_key' => 'booking-key-1',
        ])->assertStatus(201)
            ->assertJsonPath('data.nights', 2)
            ->assertJsonPath('data.rooms', 1)
            // 20 000 × 2 nuits × 1 chambre
            ->assertJsonPath('data.total_amount_minor', 40000);

        $booked = app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): array {
            return TravelHotelRoomInventory::query()
                ->forRoomRange($this->room->id, $checkIn->toDateString(), $checkIn->copy()->addDay()->toDateString())
                ->get()
                ->map(fn (TravelHotelRoomInventory $row): int => $row->rooms_booked)
                ->all();
        });

        $this->assertSame([1, 1], $booked, 'Chaque nuit de l\'intervalle doit être consommée.');
    }

    public function test_overbooking_is_rejected_with_409(): void
    {
        $checkIn = Carbon::today()->addDays(40);
        $checkOut = $checkIn->copy()->addDay();

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkIn->toDateString(),
            'rooms_total' => 1,
        ])->assertStatus(200);

        $this->postJson($this->bookingsUrl(), [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
            'idempotency_key' => 'overbook-first',
        ])->assertStatus(201);

        // La seule chambre de la nuit est vendue : la seconde demande doit être
        // refusée, et l'inventaire ne doit pas être dépassé.
        $this->postJson($this->bookingsUrl(), [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
            'idempotency_key' => 'overbook-second',
        ])->assertStatus(409);

        $booked = app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): int {
            return (int) TravelHotelRoomInventory::query()
                ->where('room_id', $this->room->id)
                ->where('stay_date', $checkIn->toDateString())
                ->value('rooms_booked');
        });

        $this->assertSame(1, $booked, 'L\'inventaire ne doit jamais dépasser rooms_total.');
    }

    public function test_refused_booking_does_not_consume_any_night(): void
    {
        $checkIn = Carbon::today()->addDays(50);
        $checkOut = $checkIn->copy()->addDays(3);

        // Nuit 1 et 3 ouvertes, nuit 2 fermée (rooms_total = 0).
        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkIn->toDateString(),
            'rooms_total' => 2,
        ])->assertStatus(200);

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->copy()->addDay()->toDateString(),
            'to' => $checkIn->copy()->addDay()->toDateString(),
            'rooms_total' => 0,
        ])->assertStatus(200);

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->copy()->addDays(2)->toDateString(),
            'to' => $checkIn->copy()->addDays(2)->toDateString(),
            'rooms_total' => 2,
        ])->assertStatus(200);

        $this->postJson($this->bookingsUrl(), [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
            'idempotency_key' => 'partial-refused',
        ])->assertStatus(409);

        $booked = app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): array {
            return TravelHotelRoomInventory::query()
                ->forRoomRange($this->room->id, $checkIn->toDateString(), $checkIn->copy()->addDays(2)->toDateString())
                ->get()
                ->map(fn (TravelHotelRoomInventory $row): int => $row->rooms_booked)
                ->all();
        });

        $this->assertSame([0, 0, 0], $booked, 'Un refus ne doit consommer aucune nuit (pas de réservation partielle).');

        $count = app(TenantManager::class)->withinTenant($this->company, function (): int {
            return TravelHotelBooking::query()->count();
        });

        $this->assertSame(0, $count, 'Aucune réservation ne doit être créée sur un refus.');
    }

    public function test_same_idempotency_key_does_not_consume_inventory_twice(): void
    {
        $checkIn = Carbon::today()->addDays(60);
        $checkOut = $checkIn->copy()->addDay();

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkIn->toDateString(),
            'rooms_total' => 2,
        ])->assertStatus(200);

        $payload = [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 1,
            'idempotency_key' => 'replay-key',
        ];

        $first = $this->postJson($this->bookingsUrl(), $payload)->assertStatus(201);
        $second = $this->postJson($this->bookingsUrl(), $payload)->assertStatus(201);

        $this->assertSame(
            $first->json('data.reference'),
            $second->json('data.reference'),
            'Un rejeu doit renvoyer la MÊME réservation.'
        );

        $booked = app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): int {
            return (int) TravelHotelRoomInventory::query()
                ->where('room_id', $this->room->id)
                ->where('stay_date', $checkIn->toDateString())
                ->value('rooms_booked');
        });

        $this->assertSame(1, $booked, 'Un rejeu ne doit pas consommer une seconde chambre.');
    }

    public function test_capacity_cannot_drop_below_already_booked(): void
    {
        $checkIn = Carbon::today()->addDays(70);
        $checkOut = $checkIn->copy()->addDay();

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkIn->toDateString(),
            'rooms_total' => 2,
        ])->assertStatus(200);

        $this->postJson($this->bookingsUrl(), [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => 2,
            'idempotency_key' => 'capacity-drop',
        ])->assertStatus(201);

        $this->putJson($this->inventoryUrl(), [
            'from' => $checkIn->toDateString(),
            'to' => $checkIn->toDateString(),
            'rooms_total' => 1,
        ])->assertStatus(422);
    }

    public function test_inventory_is_tenant_scoped(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $other->setFeature('travelagency', true);
        $other->save();

        /** @var Employee $outsider */
        $outsider = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson($this->inventoryUrl())->assertStatus(404);
    }

    public function test_simple_employee_cannot_open_inventory(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->putJson($this->inventoryUrl(), [
            'from' => Carbon::today()->addDays(80)->toDateString(),
            'to' => Carbon::today()->addDays(80)->toDateString(),
            'rooms_total' => 5,
        ])->assertStatus(403);
    }
}

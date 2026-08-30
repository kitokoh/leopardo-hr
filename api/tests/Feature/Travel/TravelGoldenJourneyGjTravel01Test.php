<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1007 (#6120) — Golden journey GJ-TRAVEL-01 : vente de billet.
 *
 * Parcours bout en bout du flux roi (MAT-013 #5871, spec §4.1) sur l'API
 * back-office v1 : recherche → consultation → réservation (pending, sièges
 * réservés) → paiement (confirmation comptant) → émission du billet →
 * check-in. Vérifie les invariants d'état à chaque étape (sièges, statuts,
 * événements outbox, PII jamais exposée) et l'isolation cross-tenant.
 *
 * Note : la variante « boutique publique » (routes /travel/shop/*,
 * TRAVEL-1001/1002) suivra quand la boutique sera sur main ; le parcours
 * métier testé ici est identique.
 */
class TravelGoldenJourneyGjTravel01Test extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    /** @var array{trip: TravelTrip, class: TravelClass, origin: TravelCity, destination: TravelCity} */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;

        $company->setFeature('travelagency', true);
        $company->save();

        $this->tenants = app(TenantManager::class);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        $this->fixture = $this->tenants->withinTenant($company, function (): array {
            /** @var TravelCity $origin */
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            /** @var TravelCity $destination */
            $destination = TravelCity::factory()->create(['name' => 'Yaoundé']);

            /** @var TravelRoute $route */
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            /** @var TravelTrip $trip */
            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 40,
            ]);

            app(GenerateTripSeatsAction::class)->execute($trip);

            /** @var TravelClass $class */
            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class, 'origin' => $origin, 'destination' => $destination];
        });
    }

    public function test_full_golden_journey_search_to_checkin(): void
    {
        $trip = $this->fixture['trip'];
        $class = $this->fixture['class'];

        // ── 1. Recherche (GET /travel/trips/search) ─────────────────────────
        $this->getJson('/api/v1/travel/trips/search?'.http_build_query([
            'origin_city_id' => $this->fixture['origin']->id,
            'destination_city_id' => $this->fixture['destination']->id,
            'departure_date' => $trip->departure_date->toDateString(),
            'status' => 'published',
        ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->where('data.0.id', $trip->id)
                ->where('data.0.status', 'published')
                ->etc());

        // ── 2. Consultation du trajet ──────────────────────────────────────
        $this->getJson("/api/v1/travel/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $trip->id)
            ->assertJsonPath('data.status', 'published');

        // ── 3. Réservation multi-passagers (POST /travel/bookings) ─────────
        $bookingResponse = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'gj-travel-01-booking',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Marie Dupont', 'age_category' => 'child', 'class_id' => $class->id],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.passenger_count', 2)
            ->assertJsonPath('data.total_amount_minor', 22500);

        $bookingId = $bookingResponse->json('data.id');

        $this->tenants->withinTenant($this->company, function () use ($trip, $bookingId): void {
            $this->assertSame(2, TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('booking_id', $bookingId)
                ->where('status', SeatStatus::RESERVED->value)
                ->count());
        });

        // ── 4. Paiement comptant (POST /bookings/{booking}/confirm) ────────
        $this->postJson("/api/v1/travel/bookings/{$bookingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);

        $this->tenants->withinTenant($this->company, function () use ($trip, $bookingId): void {
            $this->assertSame(2, TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('booking_id', $bookingId)
                ->where('status', SeatStatus::SOLD->value)
                ->count());
        });

        // ── 5. Émission des billets (POST /bookings/{booking}/issue-ticket) ─
        $ticketsResponse = $this->postJson("/api/v1/travel/bookings/{$bookingId}/issue-ticket")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ticketIds = collect($ticketsResponse->json('data'))->pluck('id')->all();
        $this->assertCount(2, $ticketIds);

        // La PII / le code de validation ne sont jamais exposés en clair
        // (règle TRAVEL-210/#6023 : seul le hash vit en base, jamais le clair).
        foreach ($ticketsResponse->json('data') as $ticket) {
            $this->assertArrayNotHasKey('validation_code', $ticket);
            $this->assertArrayNotHasKey('validation_code_hash', $ticket);
        }

        $this->tenants->withinTenant($this->company, function () use ($bookingId): void {
            $this->assertSame(2, TravelTicket::query()
                ->where('booking_id', $bookingId)
                ->where('status', TicketStatus::ISSUED->value)
                ->count());
        });

        // ── 6. Check-in des voyageurs (POST /tickets/{ticket}/check-in) ────
        foreach ($ticketIds as $ticketId) {
            $this->postJson("/api/v1/travel/tickets/{$ticketId}/check-in")
                ->assertOk()
                ->assertJsonPath('data.status', TicketStatus::CHECKED_IN->value);
        }

        // ── Invariants finaux ──────────────────────────────────────────────
        $this->tenants->withinTenant($this->company, function () use ($bookingId, $trip): void {
            $booking = TravelBooking::query()->findOrFail($bookingId);
            $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
            $this->assertSame(2, TravelTicket::query()
                ->where('booking_id', $bookingId)
                ->where('status', TicketStatus::CHECKED_IN->value)
                ->count());

            // Tous les sièges de la réservation sont vendus.
            $this->assertSame(0, TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('booking_id', $bookingId)
                ->where('status', '!=', SeatStatus::SOLD->value)
                ->count());

            // Les événements outbox jalonnent le parcours, sans doublon.
            foreach (['travel.booking.pending.v1', 'travel.booking.confirmed.v1', 'travel.ticket.issued.v1', 'travel.ticket.checked_in.v1'] as $eventType) {
                $this->assertSame(1, TravelOutboxEvent::query()->where('event_type', $eventType)->count(), "événement {$eventType} attendu");
            }
        });
    }

    public function test_golden_journey_is_isolated_cross_tenant(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($otherEmployee);

        // Un autre tenant ne voit ni le trajet ni la réservation du tenant A.
        $trip = $this->fixture['trip'];

        $this->getJson("/api/v1/travel/trips/{$trip->id}")->assertStatus(404);

        $this->tenants->withinTenant($otherCompany, function (): void {
            $this->assertSame(0, TravelBooking::query()->count());
            $this->assertSame(0, TravelOutboxEvent::query()->count());
        });
    }
}

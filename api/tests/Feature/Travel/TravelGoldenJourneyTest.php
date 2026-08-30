<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1007 (#6120) — Golden journey GJ-TRAVEL-01.
 *
 * Flux roi verrouillé (MAT-013) : recherche → réservation guichet →
 * confirmation (paiement comptant) → émission des billets → check-in.
 * Enregistré dans dev-hub/tools/golden-journeys.json (garde CI).
 */
class TravelGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /** Crée un trajet publié avec sièges + tarif adulte. */
    private function publishedTrip(Company $company): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            return $trip;
        });
    }

    public function test_golden_journey_search_booking_confirm_ticket_checkin(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company);
        $class = app(TenantManager::class)->withinTenant($company, fn () => TravelClass::first());

        // 1. Recherche de trajets (TRAVEL-311/#6041).
        $this->getJson('/api/v1/travel/trips/search')
            ->assertOk()
            ->assertJsonPath('data.0.id', $trip->id);

        // 2. Réservation au guichet, multi-passagers (TRAVEL-312/#6042).
        $bookingResponse = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'gj-travel-01-'.uniqid(),
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Awa Ndiaye', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201);

        $bookingId = $bookingResponse->json('data.id');
        $this->assertIsInt($bookingId);

        // 3. Confirmation comptant → sièges vendus (TRAVEL-313/#6043).
        $this->postJson("/api/v1/travel/bookings/{$bookingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);

        // 4. Émission des billets nominatifs (TRAVEL-316/#6046).
        $tickets = $this->postJson("/api/v1/travel/bookings/{$bookingId}/issue-ticket")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $ticketId = $tickets[0]['id'];
        $this->assertSame('issued', $tickets[0]['status']);

        // 5. Check-in du passager (TRAVEL-317/#6047).
        $this->postJson("/api/v1/travel/tickets/{$ticketId}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        // Le manifeste du trajet est accessible et contient le passager.
        $manifest = $this->getJson("/api/v1/travel/trips/{$trip->id}/manifest")
            ->assertOk()
            ->json('data');

        self::assertNotEmpty($manifest, 'le manifeste doit lister les passagers');
        self::assertContains('Jean Dupont', array_column($manifest, 'full_name'));
    }

    public function test_golden_journey_is_registered_in_registry(): void
    {
        $registry = json_decode(
            (string) file_get_contents(base_path('../dev-hub/tools/golden-journeys.json')),
            true,
        );

        self::assertIsArray($registry, 'le registre golden journeys doit exister');

        $journey = collect($registry['journeys'] ?? [])->firstWhere('id', 'GJ-TRAVEL-01');
        self::assertNotNull($journey, 'GJ-TRAVEL-01 doit être enregistré');
        self::assertSame('travelagency', $journey['solution']);
        self::assertGreaterThanOrEqual(5, count($journey['steps']));
    }
}

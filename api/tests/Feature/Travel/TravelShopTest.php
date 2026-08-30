<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-401..404 (#6053..#6056) — Boutique en ligne (auth tenant).
 *
 * Recherche (trajets publiés uniquement, filtres combinés), détail +
 * disponibilité, réservation en ligne (source online, idempotence,
 * expiration 15 min), suivi par référence.
 */
class TravelShopTest extends TestCase
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

    /**
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTrip(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            $dest = TravelCity::factory()->create(['name' => 'Yaoundé']);
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $dest->id,
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 40,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    public function test_shop_search_returns_only_published_trips(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelTrip::factory()->create(['status' => 'draft']);
        });
        $this->publishedTrip($company);

        $this->getJson('/api/v1/travel/shop/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_shop_search_filters_by_cities_and_date(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip] = $this->publishedTrip($company);
        $originId = $trip->route->origin_city_id;
        $destId = $trip->route->destination_city_id;
        $date = $trip->departure_date->toDateString();

        $this->getJson('/api/v1/travel/shop/trips?origin_city_id='.$originId.'&destination_city_id='.$destId.'&departure_date='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/travel/shop/trips?origin_city_id=999999')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_shop_detail_exposes_availability(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip] = $this->publishedTrip($company);

        $this->getJson("/api/v1/travel/shop/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.available_seats', 40);
    }

    public function test_shop_detail_rejects_draft_trip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create(['status' => 'draft'])->id;
        });

        $this->getJson("/api/v1/travel/shop/trips/{$tripId}")->assertStatus(404);
    }

    public function test_online_booking_creates_pending_with_expiration(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTrip($company);

        $this->postJson('/api/v1/travel/shop/bookings', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'shop-001',
            'passengers' => [
                ['full_name' => 'Client En Ligne', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.booking_source', BookingSource::ONLINE->value)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.expires_at', fn ($value) => $value !== null);
    }

    public function test_online_booking_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTrip($company);

        $payload = [
            'trip_id' => $trip->id,
            'idempotency_key' => 'shop-dup',
            'passengers' => [
                ['full_name' => 'Client En Ligne', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ];

        $this->postJson('/api/v1/travel/shop/bookings', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/shop/bookings', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->where('booking_source', BookingSource::ONLINE)->count();
        }));
    }

    public function test_track_booking_by_reference(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $reference = app(TenantManager::class)->withinTenant($company, function (): string {
            return TravelBooking::factory()->create()->reference;
        });

        $this->getJson("/api/v1/travel/shop/bookings/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    public function test_track_unknown_reference_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/shop/bookings/GV-UNKNOWN')->assertStatus(404);
    }

    public function test_shop_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/shop/trips')->assertStatus(401);
    }
}

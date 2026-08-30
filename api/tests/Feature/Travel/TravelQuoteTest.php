<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\QuoteStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-803 (#6094) — Devis & réservations de groupe / corporate.
 *
 * Cycle devis → réservation : total figé serveur (plafond), groupe minimal,
 * idempotence, rejet si tarifs modifiés ou devis expiré.
 */
class TravelQuoteTest extends TestCase
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
    private function publishedTripWithPrice(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function groupPassengers(TravelClass $class, int $count = 5): array
    {
        $passengers = [];
        for ($i = 0; $i < $count; $i++) {
            $passengers[] = [
                'full_name' => 'Passager Groupe '.($i + 1),
                'age_category' => 'adult',
                'class_id' => $class->id,
            ];
        }

        return $passengers;
    }

    public function test_principal_creates_quote_with_server_side_total(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-001',
            'passengers' => $this->groupPassengers($class),
        ])->assertStatus(201)
            ->assertJsonPath('data.status', QuoteStatus::DRAFT->value)
            ->assertJsonPath('data.passenger_count', 5)
            ->assertJsonPath('data.total_amount_minor', 75000) // 5 × 15000, jamais accepté du client
            ->assertJsonPath('data.currency', 'XAF');
    }

    public function test_quote_rejects_group_below_minimum_size(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-small',
            'passengers' => $this->groupPassengers($class, 2),
        ])->assertStatus(422);
    }

    public function test_same_idempotency_key_returns_existing_quote(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $payload = [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-dup',
            'passengers' => $this->groupPassengers($class),
        ];

        $this->postJson('/api/v1/travel/quotes', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/quotes', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelQuote::query()->count();
        }));
    }

    public function test_booking_quote_creates_group_booking_and_respects_ceiling(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $quote = $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-book-1',
            'passengers' => $this->groupPassengers($class),
        ])->assertStatus(201);

        $quoteId = $quote->json('data.id');

        $booking = $this->postJson("/api/v1/travel/quotes/{$quoteId}/book")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.passenger_count', 5)
            ->assertJsonPath('data.total_amount_minor', 75000);

        // Le devis est marqué réservé et lié à la réservation.
        $this->getJson("/api/v1/travel/quotes/{$quoteId}")
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::BOOKED->value)
            ->assertJsonPath('data.booking_id', $booking->json('data.id'));
    }

    public function test_booking_quote_twice_returns_same_booking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $quote = $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-book-2',
            'passengers' => $this->groupPassengers($class),
        ])->assertStatus(201);

        $quoteId = $quote->json('data.id');

        $first = $this->postJson("/api/v1/travel/quotes/{$quoteId}/book")->assertOk();
        $second = $this->postJson("/api/v1/travel/quotes/{$quoteId}/book")->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->count();
        }));
    }

    public function test_booking_quote_with_changed_prices_returns_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $quote = $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-price-1',
            'passengers' => $this->groupPassengers($class),
        ])->assertStatus(201);

        // Les tarifs changent entre la création du devis et la réservation.
        app(TenantManager::class)->withinTenant($company, function () use ($trip, $class): void {
            TravelTripPrice::query()
                ->where('trip_id', $trip->id)
                ->where('class_id', $class->id)
                ->update(['adult_price_minor' => 20000]);
        });

        $this->postJson('/api/v1/travel/quotes/'.$quote->json('data.id').'/book')
            ->assertStatus(409);
    }

    public function test_booking_expired_quote_returns_410(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $quote = $this->postJson('/api/v1/travel/quotes', [
            'trip_id' => $trip->id,
            'idempotency_key' => 'qt-exp-1',
            'passengers' => $this->groupPassengers($class),
        ])->assertStatus(201);

        app(TenantManager::class)->withinTenant($company, function () use ($quote): void {
            TravelQuote::query()
                ->where('id', $quote->json('data.id'))
                ->update(['expires_at' => now()->subDay()]);
        });

        $this->postJson('/api/v1/travel/quotes/'.$quote->json('data.id').'/book')
            ->assertStatus(410);
    }
}

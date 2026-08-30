<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-312 (#6042) — POST /travel/bookings (guichet multi-passagers).
 *
 * Couvre la création, le calcul du total depuis les tarifs (jamais accepté
 * du client), l'idempotence, la sélection explicite de sièges, le rejet
 * d'un trajet non publié, l'isolation cross-tenant et la non-exposition
 * de la PII.
 */
class TravelBookingApiTest extends TestCase
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

            // Génère l'inventaire des sièges.
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

    public function test_principal_can_create_booking_with_auto_seats(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'bk-001',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Marie Dupont', 'age_category' => 'child', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.passenger_count', 2)
            ->assertJsonPath('data.total_amount_minor', 22500) // 15000 + 7500
            ->assertJsonCount(2, 'data.passengers')
            ->assertJsonPath('data.passengers.0.seat_number', 1)
            ->assertJsonPath('data.passengers.1.seat_number', 2);

        // Les sièges sont réservés.
        $this->assertSame(2, app(TenantManager::class)->withinTenant($company, function () use ($trip): int {
            return TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', SeatStatus::RESERVED)
                ->count();
        }));
    }

    public function test_same_idempotency_key_returns_existing_booking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $payload = [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'bk-dup',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ];

        $this->postJson('/api/v1/travel/bookings', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/bookings', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->count();
        }));
    }

    public function test_explicit_seat_number_is_reserved(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'bk-seat',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id, 'seat_number' => 7],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 7);
    }

    public function test_unpublished_trip_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$tripId, $classId] = app(TenantManager::class)->withinTenant($company, function (): array {
            $class = TravelClass::factory()->create();
            $trip = TravelTrip::factory()->create(['status' => 'draft', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            return [$trip->id, $class->id];
        });

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $tripId,
            'booking_source' => 'office',
            'idempotency_key' => 'bk-draft',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $classId],
            ],
        ])->assertStatus(409);
    }

    public function test_only_one_booking_wins_the_last_seat_under_race(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Trajet avec UN SEUL siège libre : la course porte sur ce siège.
        ['trip' => $trip, 'class' => $class] = app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 1]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });

        $passenger = ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $class->id];

        // Première réservation : gagne le dernier siège (statut 201).
        $first = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'race-001',
            'passengers' => [$passenger],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 1);

        // Seconde réservation (clé d'idempotence différente) : le verrou
        // transactionnel a sérialisé l'accès → 409 SEATS_UNAVAILABLE.
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'race-002',
            'passengers' => [$passenger],
        ])->assertStatus(409);

        // Une seule réservation existe sur ce trajet, et elle possède le siège.
        [$bookingCount, $seatBookingId] = app(TenantManager::class)->withinTenant($company, function () use ($trip): array {
            return [
                TravelBooking::query()->where('trip_id', $trip->id)->count(),
                TravelTripSeat::query()->where('trip_id', $trip->id)->value('booking_id'),
            ];
        });

        $this->assertSame(1, $bookingCount);
        $this->assertSame($first->json('data.id'), $seatBookingId);
    }

    public function test_booking_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $bookingId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelBooking::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/bookings/{$bookingId}")->assertStatus(404);
    }

    public function test_document_number_is_never_exposed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'bk-pii',
            'passengers' => [
                [
                    'full_name' => 'Jean Dupont',
                    'age_category' => 'adult',
                    'class_id' => $class->id,
                    'document_type' => 'national_id',
                    'document_number' => 'CM1234567890',
                ],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.has_document', true)
            ->assertJsonMissingPath('data.passengers.0.document_number')
            ->assertJsonMissingPath('data.passengers.0.document_number_encrypted');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/bookings')->assertStatus(401);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-801 (#6092) — Assignation automatique des sièges.
 *
 * Algorithme de regroupement dans CreateBookingAction : les passagers sont
 * placés sur le plus petit bloc contigu de places libres qui les contient
 * (fenêtre avant en cas d'égalité), surclassable manuellement (seat_number
 * explicite). Couvre le regroupement, le repli, le surclassement et la
 * contention.
 */
class TravelSeatAutoAssignmentTest extends TestCase
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
     * Trajet publié avec tarif et inventaire de sièges (1..totalSeats).
     *
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTripWithPrice(Company $company, int $totalSeats = 40): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($totalSeats): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => $totalSeats]);
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
     * Occupe les sièges donnés (statut SOLD) pour modeler l'ensemble des
     * places libres.
     *
     * @param  list<int>  $seatNumbers
     */
    private function occupySeats(Company $company, TravelTrip $trip, array $seatNumbers): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($trip, $seatNumbers): void {
            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->whereIn('seat_number', $seatNumbers)
                ->update(['status' => SeatStatus::SOLD->value]);
        });
    }

    public function test_group_passengers_on_smallest_contiguous_block(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        // Places libres : 1,2 (bloc de 2) + 10,11,12 (bloc de 3). Pour 3
        // passagers, le plus petit bloc contenant le groupe est 10..12.
        $this->occupySeats($company, $trip, [3, 4, 5, 6, 7, 8, 9, 13, 14, 15, 16, 17]);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-group-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager C', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 10)
            ->assertJsonPath('data.passengers.1.seat_number', 11)
            ->assertJsonPath('data.passengers.2.seat_number', 12);
    }

    public function test_window_front_on_tie_between_blocks(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        // Deux blocs de taille 2 : 1,2 et 30,31. Pour 2 passagers, le premier
        // bloc trouvé (numéros les plus bas) est retenu → fenêtre avant.
        $this->occupySeats($company, $trip, [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29]);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-tie-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 1)
            ->assertJsonPath('data.passengers.1.seat_number', 2);
    }

    public function test_fallback_to_first_free_when_no_block_fits(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        // Aucun bloc contigu de 3 : libres 1,3,5.
        $this->occupySeats($company, $trip, [2, 4, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40]);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-fallback-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager C', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 1)
            ->assertJsonPath('data.passengers.1.seat_number', 3)
            ->assertJsonPath('data.passengers.2.seat_number', 5);
    }

    public function test_manual_override_wins_over_grouping(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-manual-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id, 'seat_number' => 25],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.passengers.0.seat_number', 25);
    }

    public function test_contention_rejects_when_not_enough_free_seats(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company, 3);

        // 3 sièges, 1 seul libre.
        $this->occupySeats($company, $trip, [1, 2]);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-contention-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(409);
    }

    public function test_assignment_is_deterministic(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company, 10);
        $this->occupySeats($company, $trip, [5, 6]);

        $payload = [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'seats-det-1',
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ];

        $this->postJson('/api/v1/travel/bookings', $payload)->assertStatus(201);

        // Même configuration → mêmes sièges (1,2 : bloc de 2, fenêtre avant).
        $this->assertSame(2, app(TenantManager::class)->withinTenant($company, function () use ($trip): int {
            return TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->whereIn('seat_number', [1, 2])
                ->where('status', SeatStatus::RESERVED->value)
                ->count();
        }));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
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
 * TRAVEL-801 (#6092) — Assignation automatique des sièges.
 *
 * Couvre : le regroupement (un groupe est placé sur un bloc CONTIGU),
 * la fenêtre avant (bloc le plus proche de la cabine), le repli quand
 * aucun bloc contigu n'existe, le surclassement manuel (agent) et la
 * contention (sièges indisponibles sautés).
 */
class TravelSeatAssignmentTest extends TestCase
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
     * Trajet publié avec N sièges et une classe tarifée.
     */
    private function publishedTrip(Company $company, int $totalSeats = 10): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($totalSeats): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => $totalSeats]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            return $trip;
        });
    }

    /**
     * @param  list<int>  $seatNumbers
     */
    private function occupy(TravelTrip $trip, array $seatNumbers): void
    {
        TravelTripSeat::query()
            ->where('trip_id', $trip->id)
            ->whereIn('seat_number', $seatNumbers)
            ->update(['status' => SeatStatus::SOLD]);
    }

    public function test_auto_assignment_keeps_group_contiguous(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company, totalSeats: 10);
        // Occupé : 1 et 5 → le premier bloc contigu de 3 est [2,3,4].
        $this->occupy($trip, [1, 5]);

        $booking = $this->createBooking($company, $trip, $this->passengers(3), 'seat-group-1', $this->principal($company));

        $seatNumbers = $booking->passengers->pluck('seat_number')->sort()->values()->all();
        $this->assertSame([2, 3, 4], $seatNumbers);
    }

    public function test_auto_assignment_prefers_front_window(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company, totalSeats: 10);
        // Occupé : 3 et 4 → le premier bloc de 2 est [1,2] (fenêtre avant).
        $this->occupy($trip, [3, 4]);

        $booking = $this->createBooking($company, $trip, $this->passengers(2), 'seat-front-1', $this->principal($company));

        $this->assertSame([1, 2], $booking->passengers->pluck('seat_number')->sort()->values()->all());
    }

    public function test_auto_assignment_falls_back_when_no_contiguous_block(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company, totalSeats: 10);
        // Occupé : 2, 4, 6, 8 → aucun bloc contigu de 3 → repli [1,3,5].
        $this->occupy($trip, [2, 4, 6, 8]);

        $booking = $this->createBooking($company, $trip, $this->passengers(3), 'seat-fallback-1', $this->principal($company));

        $this->assertSame([1, 3, 5], $booking->passengers->pluck('seat_number')->sort()->values()->all());
    }

    public function test_manual_seat_override_is_honored(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company, totalSeats: 10);

        $passengers = $this->passengers(1);
        $passengers[0]['seat_number'] = 9; // Surclassement manuel agent.

        $booking = $this->createBooking($company, $trip, $passengers, 'seat-manual-1', $this->principal($company));

        $this->assertSame([9], $booking->passengers->pluck('seat_number')->all());
    }

    public function test_auto_assignment_is_deterministic(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company, totalSeats: 10);
        $this->occupy($trip, [3, 7]);

        $passengers = $this->passengers(2);
        $actor = $this->principal($company);

        // Même clé d'idempotence : le rejeu renvoie la MÊME assignation
        // (aucun siège différent, aucun double prélèvement d'inventaire).
        $first = app(TenantManager::class)->withinTenant($company, fn () => app(CreateBookingAction::class)->execute($trip, $passengers, BookingSource::OFFICE, $actor, 'det-1'));
        $second = app(TenantManager::class)->withinTenant($company, fn () => app(CreateBookingAction::class)->execute($trip, $passengers, BookingSource::OFFICE, $actor, 'det-1'));

        $this->assertSame(
            $first->passengers->pluck('seat_number')->all(),
            $second->passengers->pluck('seat_number')->all(),
        );
        $this->assertSame($first->id, $second->id);
        // Un seul bloc de sièges réservé (pas de double réservation).
        $this->assertSame(2, TravelTripSeat::query()
            ->where('booking_id', $first->id)
            ->count());
    }

    /**
     * Exécute CreateBookingAction DANS le contexte tenant (le trait
     * BelongsToCompany remplit `company_id` depuis le tenant courant).
     *
     * @param  list<array<string, mixed>>  $passengers
     */
    private function createBooking(Company $company, TravelTrip $trip, array $passengers, string $idempotencyKey, Employee $actor): TravelBooking
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelBooking => app(CreateBookingAction::class)->execute(
            trip: $trip,
            passengers: $passengers,
            source: BookingSource::OFFICE,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
        ));
    }

    /**
     * @return list<array{full_name: string, age_category: string, class_id: int}>
     */
    private function passengers(int $count): array
    {
        $classId = TravelClass::query()->value('id');

        return array_map(fn (int $i): array => [
            'full_name' => 'Passager '.$i,
            'age_category' => 'adult',
            'class_id' => (int) $classId,
        ], range(1, $count));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-208 (#6021) — Génération transactionnelle de l'inventaire des sièges.
 *
 * Critères d'acceptation : créer un trip génère exactement `total_seats`
 * sièges ; un rejeu de la génération n'introduit aucun doublon.
 */
class TravelTripSeatGenerationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

    private GenerateTripSeatsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->tenants = app(TenantManager::class);
        $this->action = app(GenerateTripSeatsAction::class);
    }

    public function test_generation_creates_exactly_total_seats_rows(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create(['total_seats' => 42]);

            $this->action->execute($trip);

            $this->assertSame(42, TravelTripSeat::query()->where('trip_id', $trip->id)->count());
        });
    }

    public function test_generated_seats_are_numbered_from_one_and_free(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create(['total_seats' => 5]);

            $this->action->execute($trip);

            $numbers = TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->orderBy('seat_number')
                ->pluck('seat_number')
                ->all();

            $this->assertSame([1, 2, 3, 4, 5], $numbers);

            $this->assertTrue(
                TravelTripSeat::query()->where('trip_id', $trip->id)->get()
                    ->every(fn (TravelTripSeat $seat): bool => $seat->status === SeatStatus::FREE)
            );
        });
    }

    public function test_replaying_generation_does_not_duplicate_seats(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create(['total_seats' => 10]);

            $this->action->execute($trip);
            $this->action->execute($trip);
            $this->action->execute($trip);

            $this->assertSame(10, TravelTripSeat::query()->where('trip_id', $trip->id)->count());
        });
    }

    public function test_seat_number_unique_constraint_rejects_manual_duplicate(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create(['total_seats' => 5]);
            TravelTripSeat::factory()->create(['trip_id' => $trip->id, 'seat_number' => 1]);

            $this->expectException(QueryException::class);
            TravelTripSeat::factory()->create(['trip_id' => $trip->id, 'seat_number' => 1]);
        });
    }

    public function test_seats_are_isolated_per_tenant(): void
    {
        $tripA = $this->tenants->withinTenant($this->companyA, function (): TravelTrip {
            $trip = TravelTrip::factory()->create(['total_seats' => 3]);
            $this->action->execute($trip);

            return $trip;
        });

        $this->tenants->withinTenant($this->companyB, function () use ($tripA): void {
            // Un trajet du tenant A n'est pas visible pour le tenant B ; aucun
            // siège ne doit apparaître dans ce contexte tenant.
            $this->assertSame(0, TravelTripSeat::query()->where('trip_id', $tripA->id)->count());
        });
    }
}

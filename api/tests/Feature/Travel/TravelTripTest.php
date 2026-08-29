<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-207 (#6020) — Trajets datés et tarifs par classe (minor units).
 *
 * Couvre le typage des enums `TripStatus`/`MeansOfTransport`, l'unicité
 * (trip, classe) des tarifs, et les contraintes de montants > 0.
 */
class TravelTripTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

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
    }

    public function test_trip_status_defaults_to_draft(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create();

            $this->assertSame(TripStatus::DRAFT, $trip->refresh()->status);
        });
    }

    public function test_trips_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelTrip::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelTrip::query()->count());
        });
    }

    public function test_only_one_price_per_trip_and_class(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $trip = TravelTrip::factory()->create();
            $class = TravelClass::factory()->create();

            TravelTripPrice::factory()->create(['trip_id' => $trip->id, 'class_id' => $class->id]);

            $this->expectException(QueryException::class);
            TravelTripPrice::factory()->create(['trip_id' => $trip->id, 'class_id' => $class->id]);
        });
    }

    public function test_adult_price_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            TravelTripPrice::factory()->create(['adult_price_minor' => 0]);
        });
    }

    public function test_total_seats_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            TravelTrip::factory()->create(['total_seats' => 0]);
        });
    }
}

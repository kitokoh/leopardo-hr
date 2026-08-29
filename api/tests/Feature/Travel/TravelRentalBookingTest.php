<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-213 (#6026) — Réservations de location.
 *
 * Le scope `overlapping()` détecte les réservations concurrentes pour un
 * même véhicule (invariant vérifié ici) ; son application au moment de la
 * création est portée par l'Action du lot 3xx (TRAVEL-320), hors périmètre
 * schéma.
 */
class TravelRentalBookingTest extends TestCase
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

    public function test_overlapping_scope_detects_conflicting_dates(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicle = TravelRentalVehicle::factory()->create();

            TravelRentalBooking::factory()->create([
                'vehicle_id' => $vehicle->id,
                'start_date' => '2027-01-10',
                'end_date' => '2027-01-15',
            ]);

            $overlapCount = TravelRentalBooking::query()
                ->overlapping($vehicle->id, '2027-01-12', '2027-01-20')
                ->count();

            $this->assertSame(1, $overlapCount);
        });
    }

    public function test_overlapping_scope_ignores_non_conflicting_dates(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicle = TravelRentalVehicle::factory()->create();

            TravelRentalBooking::factory()->create([
                'vehicle_id' => $vehicle->id,
                'start_date' => '2027-01-10',
                'end_date' => '2027-01-15',
            ]);

            $overlapCount = TravelRentalBooking::query()
                ->overlapping($vehicle->id, '2027-02-01', '2027-02-05')
                ->count();

            $this->assertSame(0, $overlapCount);
        });
    }

    public function test_overlapping_scope_ignores_cancelled_bookings(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicle = TravelRentalVehicle::factory()->create();

            TravelRentalBooking::factory()->create([
                'vehicle_id' => $vehicle->id,
                'start_date' => '2027-01-10',
                'end_date' => '2027-01-15',
                'status' => 'cancelled',
            ]);

            $overlapCount = TravelRentalBooking::query()
                ->overlapping($vehicle->id, '2027-01-12', '2027-01-20')
                ->count();

            $this->assertSame(0, $overlapCount);
        });
    }

    public function test_reference_is_generated_automatically(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $booking = TravelRentalBooking::factory()->create();

            $this->assertStringStartsWith('RB-', $booking->reference);
        });
    }

    public function test_bookings_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelRentalBooking::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelRentalBooking::query()->count());
        });
    }
}

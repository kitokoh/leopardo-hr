<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelDailySale;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripOccupancy;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-506 (#6076) — Read models recalculables : reprise du job → état
 * identique (idempotence), agrégats exacts.
 */
class TravelReadModelsTest extends TestCase
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

    public function test_recalculate_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = app(TenantManager::class)->withinTenant($company, fn (): TravelTrip => TravelTrip::factory()->create([
            'status' => 'published',
            'total_seats' => 40,
        ]));

        app(TenantManager::class)->withinTenant($company, function () use ($trip): void {
            TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'passenger_count' => 3,
                'total_amount_minor' => 30000,
            ]);
        });

        $this->artisan('travel:recalculate-read-models')->assertSuccessful();
        $this->artisan('travel:recalculate-read-models')->assertSuccessful();

        $sales = app(TenantManager::class)->withinTenant($company, fn (): int => TravelDailySale::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, $sales);

        $daily = app(TenantManager::class)->withinTenant($company, fn () => TravelDailySale::query()->where('company_id', $company->id)->first());
        $this->assertSame(1, (int) $daily->bookings_count);
        $this->assertSame(3, (int) $daily->passengers_count);
        $this->assertSame(30000, (int) $daily->revenue_minor);

        $occ = app(TenantManager::class)->withinTenant($company, fn () => TravelTripOccupancy::query()->where('company_id', $company->id)->first());
        $this->assertSame(3, (int) $occ->seats_sold);
        $this->assertSame(40, (int) $occ->total_seats);
        $this->assertSame(0.075, (float) $occ->occupancy_rate);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-214 (#6027) — Catalogue hôtelier.
 *
 * Couvre la classification bornée (1-5), l'unicité du numéro de chambre par
 * hôtel et l'isolation cross-tenant.
 */
class TravelHotelTest extends TestCase
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

    public function test_classification_must_be_between_one_and_five(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            TravelHotel::factory()->create(['classification' => 6]);
        });
    }

    public function test_classification_zero_is_rejected(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            TravelHotel::factory()->create(['classification' => 0]);
        });
    }

    public function test_hotels_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelHotel::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelHotel::query()->count());
        });
    }

    public function test_room_number_unique_per_hotel(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $hotel = TravelHotel::factory()->create();
            TravelHotelRoom::factory()->create(['hotel_id' => $hotel->id, 'room_number' => '101']);

            $this->expectException(QueryException::class);
            TravelHotelRoom::factory()->create(['hotel_id' => $hotel->id, 'room_number' => '101']);
        });
    }

    public function test_same_room_number_allowed_on_different_hotels(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $hotelA = TravelHotel::factory()->create();
            $hotelB = TravelHotel::factory()->create();

            TravelHotelRoom::factory()->create(['hotel_id' => $hotelA->id, 'room_number' => '101']);
            TravelHotelRoom::factory()->create(['hotel_id' => $hotelB->id, 'room_number' => '101']);

            $this->assertSame(2, TravelHotelRoom::query()->count());
        });
    }

    public function test_room_capacity_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            TravelHotelRoom::factory()->create(['capacity' => 0]);
        });
    }
}

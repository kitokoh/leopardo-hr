<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-321 (#6051) — CRUD hôtels + chambres + recherche par ville.
 */
class TravelHotelApiTest extends TestCase
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

    public function test_principal_can_create_hotel_and_room(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/hotels', [
            'name' => 'Hôtel Azur',
            'city_id' => $cityId,
            'classification' => 4,
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Hôtel Azur', 'classification' => 4]);

        $hotelId = app(TenantManager::class)->withinTenant($company, function () use ($cityId): int {
            return TravelHotel::factory()->create(['city_id' => $cityId])->id;
        });

        $this->postJson("/api/v1/travel/hotels/{$hotelId}/rooms", [
            'type_code' => 'SGL',
            'room_number' => '101',
            'capacity' => 2,
            'price_per_night_minor' => 20000,
            'currency' => 'XAF',
        ])->assertStatus(201)
            ->assertJsonFragment(['room_number' => '101', 'price_per_night_minor' => 20000]);
    }

    public function test_classification_out_of_range_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/hotels', [
            'name' => 'Hôtel 6 étoiles',
            'city_id' => $cityId,
            'classification' => 6,
        ])->assertStatus(422);
    }

    public function test_hotel_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $hotelId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelHotel::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/hotels/{$hotelId}")->assertStatus(404);
    }

    public function test_room_on_other_tenant_hotel_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $hotelId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelHotel::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/hotels/{$hotelId}/rooms", [
            'type_code' => 'SGL',
            'room_number' => '101',
            'capacity' => 2,
            'price_per_night_minor' => 20000,
            'currency' => 'XAF',
        ])->assertStatus(404);
    }

    public function test_hotels_filtered_by_city(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$cityA, $cityB] = app(TenantManager::class)->withinTenant($company, function (): array {
            $cityA = TravelCity::factory()->create();
            $cityB = TravelCity::factory()->create();
            TravelHotel::factory()->create(['city_id' => $cityA->id]);
            TravelHotel::factory()->create(['city_id' => $cityB->id]);

            return [$cityA->id, $cityB->id];
        });

        $this->getJson('/api/v1/travel/hotels?city_id='.$cityA)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_and_delete_hotel(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $hotelId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelHotel::factory()->create()->id;
        });

        $this->putJson("/api/v1/travel/hotels/{$hotelId}", ['classification' => 5])
            ->assertOk()
            ->assertJsonFragment(['classification' => 5]);

        $this->deleteJson("/api/v1/travel/hotels/{$hotelId}")->assertStatus(204);
    }
}

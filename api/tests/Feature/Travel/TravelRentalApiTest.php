<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\RentalBookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-319/320 (#6049/#6050) — CRUD véhicules en location + réservations
 * avec contrôle de non-chevauchement (409).
 */
class TravelRentalApiTest extends TestCase
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

    public function test_principal_can_create_rental_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/rental-vehicles', [
            'code' => 'RV-001',
            'title' => 'Toyota RAV4',
            'city_id' => $cityId,
            'price_per_day_minor' => 25000,
            'currency' => 'XAF',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'RV-001', 'price_per_day_minor' => 25000]);
    }

    public function test_rental_vehicle_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $vehicleId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelRentalVehicle::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/rental-vehicles/{$vehicleId}")->assertStatus(404);
    }

    public function test_create_rental_booking_computes_total(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalVehicle::factory()->create(['price_per_day_minor' => 10000, 'currency' => 'XAF'])->id;
        });

        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(4)->toDateString(); // 3 jours

        $this->postJson('/api/v1/travel/rental-bookings', [
            'vehicle_id' => $vehicleId,
            'start_date' => $start,
            'end_date' => $end,
            'idempotency_key' => 'rb-001',
        ])->assertStatus(201)
            ->assertJsonPath('data.total_amount_minor', 30000)
            ->assertJsonPath('data.status', RentalBookingStatus::PENDING->value);
    }

    public function test_overlapping_rental_booking_returns_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalVehicle::factory()->create(['price_per_day_minor' => 10000, 'currency' => 'XAF'])->id;
        });

        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(4)->toDateString();

        $payload = [
            'vehicle_id' => $vehicleId,
            'start_date' => $start,
            'end_date' => $end,
            'idempotency_key' => 'rb-001',
        ];

        $this->postJson('/api/v1/travel/rental-bookings', $payload)->assertStatus(201);

        // Chevauchement partiel (même véhicule, période imbriquée).
        $this->postJson('/api/v1/travel/rental-bookings', [
            ...$payload,
            'idempotency_key' => 'rb-002',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->assertStatus(409);
    }

    public function test_rental_booking_idempotency(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalVehicle::factory()->create(['price_per_day_minor' => 10000, 'currency' => 'XAF'])->id;
        });

        $payload = [
            'vehicle_id' => $vehicleId,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'idempotency_key' => 'rb-dup',
        ];

        $this->postJson('/api/v1/travel/rental-bookings', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/rental-bookings', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalBooking::query()->count();
        }));
    }

    public function test_cancel_rental_booking_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $bookingId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalBooking::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/rental-bookings/{$bookingId}/cancel")
            ->assertStatus(422);

        $this->postJson("/api/v1/travel/rental-bookings/{$bookingId}/cancel", ['reason' => 'Client désisté'])
            ->assertOk()
            ->assertJsonPath('data.status', RentalBookingStatus::CANCELLED->value);
    }
}

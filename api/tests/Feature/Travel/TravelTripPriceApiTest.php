<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-309 (#6039) — CRUD /travel/trips/{trip}/prices (tarifs par classe).
 *
 * Couvre le CRUD, les unités mineures strictement positives, l'unicité
 * (trip, classe) → 409, l'isolation cross-tenant.
 */
class TravelTripPriceApiTest extends TestCase
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

    public function test_principal_can_create_price_for_trip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$tripId, $classId] = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                TravelTrip::factory()->create()->id,
                TravelClass::factory()->create()->id,
            ];
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/prices", [
            'class_id' => $classId,
            'adult_price_minor' => 15000,
            'child_price_minor' => 7500,
            'currency' => 'XAF',
        ])->assertStatus(201)
            ->assertJsonFragment(['adult_price_minor' => 15000, 'currency' => 'XAF']);
    }

    public function test_duplicate_price_for_same_class_returns_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$tripId, $classId] = app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create();
            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create(['trip_id' => $trip->id, 'class_id' => $class->id]);

            return [$trip->id, $class->id];
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/prices", [
            'class_id' => $classId,
            'adult_price_minor' => 20000,
            'currency' => 'XAF',
        ])->assertStatus(409);
    }

    public function test_price_must_be_positive(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/prices", [
            'class_id' => 999999,
            'adult_price_minor' => 0,
            'currency' => 'XAF',
        ])->assertStatus(422);
    }

    public function test_price_on_other_tenant_trip_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $tripId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/trips/{$tripId}/prices")->assertStatus(404);
    }

    public function test_update_and_delete_price(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $priceId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTripPrice::factory()->create()->id;
        });
        $tripId = app(TenantManager::class)->withinTenant($company, function () use ($priceId): int {
            return TravelTripPrice::query()->findOrFail($priceId)->trip_id;
        });

        $this->putJson("/api/v1/travel/trips/{$tripId}/prices/{$priceId}", [
            'adult_price_minor' => 18000,
        ])->assertOk()
            ->assertJsonFragment(['adult_price_minor' => 18000]);

        $this->deleteJson("/api/v1/travel/trips/{$tripId}/prices/{$priceId}")->assertStatus(204);
    }
}

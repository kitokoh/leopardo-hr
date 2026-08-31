<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-308 (#6038) — CRUD /travel/trips + génération transactionnelle
 * des sièges (TRAVEL-208).
 *
 * Couvre le CRUD, la génération des sièges à la création, l'isolation
 * cross-tenant et le verrouillage d'un trajet publié.
 */
class TravelTripApiTest extends TestCase
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

    public function test_principal_can_create_trip_with_seat_generation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/trips', [
            'code' => 'TRP-2026-001',
            'route_id' => $routeId,
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:30',
            'means_of_transport' => 'bus',
            'total_seats' => 40,
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'TRP-2026-001', 'total_seats' => 40]);

        // L'inventaire des sièges est généré transactionnellement.
        $this->assertSame(40, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTripSeat::query()->count();
        }));
    }

    public function test_trip_requires_route(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/trips', [
            'code' => 'TRP-NO-ROUTE',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:30',
            'total_seats' => 40,
        ])->assertStatus(422);
    }

    public function test_total_seats_must_be_positive(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/trips', [
            'code' => 'TRP-ZERO',
            'route_id' => $routeId,
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:30',
            'total_seats' => 0,
        ])->assertStatus(422);
    }

    public function test_trip_of_another_tenant_returns_404(): void
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

        $this->getJson("/api/v1/travel/trips/{$tripId}")->assertStatus(404);
    }

    public function test_published_trip_cannot_be_updated_directly(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create(['status' => 'published'])->id;
        });

        $this->putJson("/api/v1/travel/trips/{$tripId}", ['total_seats' => 30])
            ->assertStatus(422);
    }

    public function test_update_and_delete_trip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $this->putJson("/api/v1/travel/trips/{$tripId}", ['total_seats' => 55])
            ->assertOk()
            ->assertJsonFragment(['total_seats' => 55]);

        $this->deleteJson("/api/v1/travel/trips/{$tripId}")->assertStatus(204);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/trips')->assertStatus(401);
    }
}

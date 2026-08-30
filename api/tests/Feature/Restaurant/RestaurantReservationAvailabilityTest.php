<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-602 (#6207) — Disponibilité de créneaux (tables, couverts, dates).
 *
 * Couvre : filtre par capacité (covers ≤ capacity), exclusion des tables
 * déjà réservées sur le créneau (±2h) et branche hors tenant → 404.
 */
class RestaurantReservationAvailabilityTest extends TestCase
{
    use RefreshTenantDatabase;

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_availability_filters_by_capacity_and_conflicts(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $context = app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $small = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'label' => 'T1', 'capacity' => 2]);
            $large = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'label' => 'T2', 'capacity' => 8]);

            return ['branch' => $branch, 'small' => $small, 'large' => $large];
        });

        $slot = now()->addDays(2)->setTime(20, 0);

        // T2 est réservée sur ce créneau.
        app(TenantManager::class)->withinTenant($company, function () use ($context, $slot): void {
            RestaurantReservation::query()->create([
                'branch_id' => $context['branch']->id,
                'table_id' => $context['large']->id,
                'contact_name' => 'Test',
                'contact_phone' => '+237600000000',
                'reserved_at' => $slot,
                'covers' => 4,
                'status' => 'confirmed',
            ]);
        });

        // 6 couverts : seule T2 a la capacité, mais elle est réservée → vide.
        $response = $this->getJson('/api/v1/restaurant/reservations/availability?'.http_build_query([
            'branch_id' => $context['branch']->id,
            'reserved_at' => $slot->toIso8601String(),
            'covers' => 6,
        ]))->assertStatus(200)->json('data');

        $this->assertSame(6, $response['covers']);
        $this->assertCount(0, $response['available_tables']);

        // 2 couverts : T1 disponible (T2 réservée → exclue).
        $response2 = $this->getJson('/api/v1/restaurant/reservations/availability?'.http_build_query([
            'branch_id' => $context['branch']->id,
            'reserved_at' => $slot->toIso8601String(),
            'covers' => 2,
        ]))->assertStatus(200)->json('data');

        $this->assertCount(1, $response2['available_tables']);
        $this->assertSame($context['small']->id, $response2['available_tables'][0]['id']);
    }

    public function test_availability_other_tenant_branch_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $otherBranchId = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): int => RestaurantBranch::factory()->create()->id
        );

        $this->getJson('/api/v1/restaurant/reservations/availability?branch_id='.$otherBranchId.'&reserved_at='.now()->addDay()->toIso8601String().'&covers=2')
            ->assertStatus(404);
    }

    public function test_availability_requires_params(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $this->getJson('/api/v1/restaurant/reservations/availability')->assertStatus(422);
    }
}

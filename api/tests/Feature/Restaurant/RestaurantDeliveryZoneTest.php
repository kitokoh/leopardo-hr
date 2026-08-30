<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-604 (#6209) — Zones de livraison + frais.
 *
 * CRUD tenant-scopé + `quote` : frais calculés serveur selon la zone et le
 * montant de commande (éligibilité par commande minimum).
 */
class RestaurantDeliveryZoneTest extends TestCase
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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_zone_crud_and_quote(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();

            $this->postJson('/api/v1/restaurant/delivery-zones', [
                'branch_id' => $branch->id,
                'name' => 'Centre-ville',
                'fee_minor' => 1500,
                'min_order_minor' => 5000,
            ])->assertStatus(201)
                ->assertJsonFragment(['name' => 'Centre-ville', 'fee_minor' => 1500]);

            $zoneId = RestaurantDeliveryZone::query()->firstOrFail()->id;

            // Sous le minimum → non éligible, pas de frais.
            $this->getJson("/api/v1/restaurant/delivery-zones/{$zoneId}/quote?order_total_minor=3000")
                ->assertOk()
                ->assertJsonPath('data.eligible', false)
                ->assertJsonPath('data.fee_minor', 0);

            // Au-dessus du minimum → éligible, frais appliqués serveur.
            $this->getJson("/api/v1/restaurant/delivery-zones/{$zoneId}/quote?order_total_minor=8000")
                ->assertOk()
                ->assertJsonPath('data.eligible', true)
                ->assertJsonPath('data.fee_minor', 1500);

            // Zone d'un autre tenant → 404.
            /** @var Company $other */
            $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
            $otherZoneId = app(TenantManager::class)->withinTenant($other, fn (): int => RestaurantDeliveryZone::factory()->create()->id);

            $this->getJson("/api/v1/restaurant/delivery-zones/{$otherZoneId}")->assertStatus(404);
        });
    }
}

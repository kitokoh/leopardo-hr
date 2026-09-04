<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-102 (#6159) — Gate de la verticale RestaurantManager.
 *
 * La route de smoke test `GET /api/v1/restaurant/ping` doit être :
 *   - inaccessible sans authentification (401) ;
 *   - refusée quand le feature flag `restaurantmanager` est inactif (403) ;
 *   - accessible quand le flag est actif (200 {status: ok}).
 */
class RestaurantFeatureFlagTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_ping_requires_authentication(): void
    {
        $this->getJson('/api/v1/restaurant/ping')
            ->assertStatus(401);
    }

    public function test_ping_is_rejected_when_feature_flag_disabled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/restaurant/ping')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_ping_is_available_when_feature_flag_enabled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/restaurant/ping')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'restaurantmanager',
            ]);
    }
}

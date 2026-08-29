<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-102 (#6007) — Gate de la verticale TravelAgency.
 *
 * La route de smoke test `GET /api/v1/travel/ping` doit être :
 *   - inaccessible sans authentification (401) ;
 *   - refusée quand le feature flag `travelagency` est inactif (403) ;
 *   - accessible quand le flag est actif (200 {status: ok}).
 */
class TravelFeatureFlagTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_ping_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/ping')
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

        $this->getJson('/api/v1/travel/ping')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_ping_is_available_when_feature_flag_enabled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/travel/ping')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'travelagency',
            ]);
    }
}

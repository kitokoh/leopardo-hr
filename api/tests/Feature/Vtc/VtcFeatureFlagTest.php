<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * VTC-01 (#8357, BC-34 VTC) — gate de la verticale VTC/taxi.
 *
 * La route de smoke test `GET /api/v1/vtc/ping` doit être :
 *   - inaccessible sans authentification (401) ;
 *   - refusée quand le feature flag `vtc` est inactif (403) ;
 *   - accessible quand le flag est actif (200 {status: ok}).
 */
class VtcFeatureFlagTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_ping_requires_authentication(): void
    {
        $this->getJson('/api/v1/vtc/ping')
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

        $this->getJson('/api/v1/vtc/ping')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_ping_is_available_when_feature_flag_enabled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('vtc', true);
        $company->save();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/vtc/ping')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'vtc',
            ]);
    }
}

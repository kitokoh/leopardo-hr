<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-05 (#8354, BC-33 GEO) — endpoint `GET /api/v1/geo/capabilities`.
 *
 * Réservé admin tenant (garde `geo.admin`, deny-by-default) :
 *   - 401 sans authentification ;
 *   - 403 quand le feature flag `geo` est inactif ;
 *   - 403 GEO_ADMIN_REQUIRED pour un manager non principal ou un employé ;
 *   - 200 {data:{postgis_available, postgis_version, distance_engine}}
 *     cohérent : moteur `postgis` ssi l'extension est disponible.
 */
class GeoCapabilitiesApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_capabilities_requires_authentication(): void
    {
        $this->getJson('/api/v1/geo/capabilities')
            ->assertStatus(401);
    }

    public function test_capabilities_is_rejected_when_feature_flag_disabled(): void
    {
        $this->actingAsTenantEmployee(managerRole: 'principal');

        $this->getJson('/api/v1/geo/capabilities')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_capabilities_requires_admin_role(): void
    {
        // Manager non principal : refusé (deny-by-default).
        $this->actingAsTenantEmployee(managerRole: 'manager', enableGeo: true);

        $this->getJson('/api/v1/geo/capabilities')
            ->assertStatus(403)
            ->assertJson(['error' => 'GEO_ADMIN_REQUIRED']);
    }

    public function test_capabilities_returns_engine_state_for_admin(): void
    {
        $this->actingAsTenantEmployee(managerRole: 'principal', enableGeo: true);

        $response = $this->getJson('/api/v1/geo/capabilities')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['postgis_available', 'postgis_version', 'distance_engine'],
            ]);

        /** @var bool $postgisAvailable */
        $postgisAvailable = $response->json('data.postgis_available');

        // Cohérence moteur ↔ extension : postgis ssi l'extension est là.
        self::assertSame(
            $postgisAvailable ? 'postgis' : 'haversine',
            $response->json('data.distance_engine')
        );
    }

    private function actingAsTenantEmployee(string $managerRole, bool $enableGeo = false): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableGeo) {
            $company->setFeature('geo', true);
            $company->save();
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $company;
    }
}

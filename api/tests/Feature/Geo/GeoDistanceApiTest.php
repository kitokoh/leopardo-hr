<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-05 (#8354, BC-33 GEO) — endpoint `POST /api/v1/geo/distance`.
 *
 *   - 401 sans authentification ;
 *   - 403 quand le feature flag `geo` est inactif ;
 *   - 422 sur coordonnées invalides (bornes WGS 84, points requis) ;
 *   - 200 {data:{distance_m}} — distance géodésique (moteur PostGIS en CI
 *     PG16, repli Haversine ailleurs : assertion bornée, écart < 2 %).
 */
class GeoDistanceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_distance_requires_authentication(): void
    {
        $this->postJson('/api/v1/geo/distance', [])
            ->assertStatus(401);
    }

    public function test_distance_is_rejected_when_feature_flag_disabled(): void
    {
        $this->actingAsTenantEmployee();

        $this->postJson('/api/v1/geo/distance', [
            'from' => ['lat' => 48.8566, 'lng' => 2.3522],
            'to' => ['lat' => 45.7640, 'lng' => 4.8357],
        ])->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_distance_validates_coordinates(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        // Points manquants.
        $this->postJson('/api/v1/geo/distance', [])->assertStatus(422);

        // Latitude hors bornes.
        $this->postJson('/api/v1/geo/distance', [
            'from' => ['lat' => 95.0, 'lng' => 2.3522],
            'to' => ['lat' => 45.7640, 'lng' => 4.8357],
        ])->assertStatus(422);

        // Longitude hors bornes.
        $this->postJson('/api/v1/geo/distance', [
            'from' => ['lat' => 48.8566, 'lng' => 2.3522],
            'to' => ['lat' => 45.7640, 'lng' => 200.0],
        ])->assertStatus(422);
    }

    public function test_distance_returns_geodesic_distance_in_meters(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        // Paris (Notre-Dame) → Lyon (place Bellecour) : ~392 km à vol
        // d'oiseau. Tolérance ±2 % (sphère Haversine vs ellipsoïde PostGIS).
        $this->postJson('/api/v1/geo/distance', [
            'from' => ['lat' => 48.8566, 'lng' => 2.3522],
            'to' => ['lat' => 45.7640, 'lng' => 4.8357],
        ])->assertOk()
            ->assertJsonStructure(['data' => ['distance_m']])
            ->assertJson(fn (\Illuminate\Testing\Fluent\AssertableJson $json) => $json
                ->where('data.distance_m', fn (int $meters): bool => $meters > 384_000 && $meters < 400_000)
                ->etc());
    }

    public function test_distance_between_identical_points_is_zero(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        $this->postJson('/api/v1/geo/distance', [
            'from' => ['lat' => 48.8566, 'lng' => 2.3522],
            'to' => ['lat' => 48.8566, 'lng' => 2.3522],
        ])->assertOk()
            ->assertJson(['data' => ['distance_m' => 0]]);
    }

    private function actingAsTenantEmployee(bool $enableGeo = false): Company
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
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $company;
    }
}

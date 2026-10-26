<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Geo\Domain\Contracts\GeocodingProviderInterface;
use App\Modules\Geo\Infrastructure\Services\GeocodingService;
use App\Shared\Geo\GeoPoint;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-08 (#8466, BC-34 GEO) — endpoint `POST /api/v1/geo/geocode`.
 *
 *   - 401 sans authentification ;
 *   - 403 quand le feature flag `geo` est inactif ;
 *   - 422 sur adresse invalide (trop courte, code pays mal formé) ;
 *   - 503 `geocoding_disabled` sans fournisseur réel (fail-closed : le core
 *     n'invente JAMAIS de coordonnées) ;
 *   - 200 {data:{latitude,longitude,provider,cached}} avec un fournisseur
 *     branché, et 404 `address_not_found` quand l'adresse n'est pas résolue.
 */
class GeoGeocodeApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_geocode_requires_authentication(): void
    {
        $this->postJson('/api/v1/geo/geocode', ['address' => 'Paris, France'])
            ->assertStatus(401);
    }

    public function test_geocode_is_rejected_when_feature_flag_disabled(): void
    {
        $this->actingAsTenantEmployee();

        $this->postJson('/api/v1/geo/geocode', ['address' => 'Paris, France'])
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_geocode_validates_address(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        // Adresse manquante.
        $this->postJson('/api/v1/geo/geocode', [])->assertStatus(422);

        // Adresse trop courte.
        $this->postJson('/api/v1/geo/geocode', ['address' => 'ab'])->assertStatus(422);

        // Code pays mal formé (3 lettres).
        $this->postJson('/api/v1/geo/geocode', [
            'address' => 'Paris, France',
            'country_code' => 'FRA',
        ])->assertStatus(422);
    }

    public function test_geocode_is_fail_closed_without_provider(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        // Aucun fournisseur réel branché : refus explicite, jamais de
        // coordonnées inventées.
        $this->postJson('/api/v1/geo/geocode', ['address' => 'Paris, France'])
            ->assertStatus(503)
            ->assertJson(['error' => ['code' => 'geocoding_disabled']]);
    }

    public function test_geocode_returns_coordinates_with_provider(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        $this->app->bind(GeocodingProviderInterface::class, fn (): GeocodingProviderInterface => new class implements GeocodingProviderInterface
        {
            public function geocode(string $address, ?string $countryCode = null): ?GeoPoint
            {
                return $address === 'Paris, France' ? new GeoPoint(48.8566, 2.3522) : null;
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        $this->app->forgetInstance(GeocodingService::class);

        $this->postJson('/api/v1/geo/geocode', ['address' => 'Paris, France'])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                    'provider' => 'fake',
                    'cached' => false,
                ],
            ]);
    }

    public function test_geocode_returns_404_when_address_is_not_resolved(): void
    {
        $this->actingAsTenantEmployee(enableGeo: true);

        $this->app->bind(GeocodingProviderInterface::class, fn (): GeocodingProviderInterface => new class implements GeocodingProviderInterface
        {
            public function geocode(string $address, ?string $countryCode = null): ?GeoPoint
            {
                return null;
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        $this->app->forgetInstance(GeocodingService::class);

        $this->postJson('/api/v1/geo/geocode', ['address' => 'Adresse Inexistante 42'])
            ->assertStatus(404)
            ->assertJson(['error' => ['code' => 'address_not_found']]);
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

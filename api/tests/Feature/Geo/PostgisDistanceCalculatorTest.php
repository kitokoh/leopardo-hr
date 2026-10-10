<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Modules\Geo\Infrastructure\Services\GeoCapabilities;
use App\Modules\Geo\Infrastructure\Services\HaversineDistanceCalculator;
use App\Modules\Geo\Infrastructure\Services\PostgisDistanceCalculator;
use App\Shared\Geo\GeoPoint;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-03 (#8352, BC-33 GEO) — calculateur PostGIS de référence et écart
 * avec le fallback Haversine (< 1 % sous 100 km, critère de l'issue).
 *
 * Skippé hors PostgreSQL provisionné (le fallback est couvert en unitaire).
 */
class PostgisDistanceCalculatorTest extends TestCase
{
    use RefreshTenantDatabase;

    private function postgisReady(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        /** @var GeoCapabilities $capabilities */
        $capabilities = $this->app->make(GeoCapabilities::class);
        $capabilities->forget();

        return $capabilities->postgisAvailable();
    }

    public function test_postgis_distance_on_known_route(): void
    {
        if (! $this->postgisReady()) {
            $this->markTestSkipped('PostGIS indisponible sur cet environnement.');
        }

        $paris = new GeoPoint(48.8566, 2.3522);
        $lyon = new GeoPoint(45.764, 4.8357);

        $distance = (new PostgisDistanceCalculator)->distance($paris, $lyon)->meters();

        // Référence géodésique ellipsoïdale ≈ 392 km — tolérance ±1 %.
        self::assertGreaterThan(388000, $distance);
        self::assertLessThan(396000, $distance);
        self::assertSame('postgis', (new PostgisDistanceCalculator)->engine());
    }

    public function test_postgis_and_haversine_diverge_less_than_one_percent_under_100_km(): void
    {
        if (! $this->postgisReady()) {
            $this->markTestSkipped('PostGIS indisponible sur cet environnement.');
        }

        $postgis = new PostgisDistanceCalculator;
        $haversine = new HaversineDistanceCalculator;

        // Trajets < 100 km (Paris→Saint-Denis ≈ 9 km, Paris→Fontainebleau ≈ 55 km).
        $routes = [
            [new GeoPoint(48.8566, 2.3522), new GeoPoint(48.9362, 2.3574)],
            [new GeoPoint(48.8566, 2.3522), new GeoPoint(48.4047, 2.7016)],
        ];

        foreach ($routes as [$from, $to]) {
            $reference = $postgis->distance($from, $to)->meters();
            $fallback = $haversine->distance($from, $to)->meters();

            self::assertGreaterThan(0, $reference);

            $divergence = abs($fallback - $reference) / $reference;

            self::assertLessThan(
                0.01,
                $divergence,
                "Écart PostGIS/Haversine {$divergence} ≥ 1 % sur {$reference} m."
            );
        }
    }
}

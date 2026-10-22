<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Farm OS — garde de NON-RÉGRESSION de la surface HTTP.
 *
 * Vérifie que la verticale n'expose RIEN hors de son préfixe `farm` : une route
 * agricole montée ailleurs échapperait au middleware `module.farm_os` et donc
 * au gate de tenant. Test booté (konteneur + routeur réels), sans base : il
 * tourne aussi là où PostgreSQL est absent.
 */
final class FarmOsRouteSurfaceTest extends TestCase
{
    /**
     * `RouteCollection` est declare `RouteCollectionInterface` cote Laravel, qui
     * n'etend pas `IteratorAggregate` : PHPStan refuse un `foreach` direct
     * (foreach.nonIterable). On passe par `getRoutes()` type explicitement.
     *
     * @return iterable<\Illuminate\Routing\Route>
     */
    private function routes(): iterable
    {
        /** @var \Illuminate\Routing\RouteCollection $collection */
        $collection = Route::getRoutes();

        return $collection->getRoutes();
    }

    /**
     * @return list<string>
     */
    private function farmUris(): array
    {
        $uris = [];

        foreach ($this->routes() as $route) {
            $uri = $route->uri();

            if (str_contains($uri, 'farm')) {
                $uris[] = $uri;
            }
        }

        sort($uris);

        return $uris;
    }

    public function test_farm_routes_are_exposed(): void
    {
        $uris = $this->farmUris();

        $this->assertNotSame([], $uris, 'Aucune route Farm OS : le module a-t-il été débranché ?');
    }

    public function test_every_farm_route_stays_under_the_farm_prefix(): void
    {
        $leaks = array_values(array_filter(
            $this->farmUris(),
            static fn (string $uri): bool => ! str_starts_with($uri, 'api/v1/farm/') && $uri !== 'api/v1/farm'
        ));

        $this->assertSame(
            [],
            $leaks,
            "Routes Farm OS hors du préfixe `farm` (fuite de surface multi-tenant) :\n - ".implode("\n - ", $leaks)
        );
    }

    public function test_no_duplicate_farm_route_is_declared(): void
    {
        // Un GET et un POST sur la meme URI sont parfaitement legitimes : le
        // doublon se juge sur le couple (methode, URI), pas sur l'URI seule.
        $duplicates = array_keys(array_filter(
            array_count_values($this->farmRouteSignatures()),
            static fn (int $n): bool => $n > 1
        ));

        $this->assertSame(
            [],
            $duplicates,
            "Routes Farm OS declarees deux fois :\n - ".implode("\n - ", $duplicates)
        );
    }

    /**
     * @return list<string>
     */
    private function farmRouteSignatures(): array
    {
        $signatures = [];

        foreach ($this->routes() as $route) {
            $uri = $route->uri();

            if (! str_contains($uri, 'farm')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $signatures[] = $method.' '.$uri;
            }
        }

        sort($signatures);

        return $signatures;
    }

    /**
     * Les routes du module doivent porter le middleware de gate : sans lui un
     * tenant non abonné à Farm OS accéderait aux données agricoles.
     */
    public function test_farm_routes_are_gated_by_the_module_middleware(): void
    {
        $ungated = [];

        foreach ($this->routes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/farm/')) {
                continue;
            }

            if (! in_array('module.farm_os', $route->gatherMiddleware(), true)) {
                $ungated[] = $uri;
            }
        }

        $this->assertSame(
            [],
            $ungated,
            "Routes Farm OS sans gate `module.farm_os` :\n - ".implode("\n - ", $ungated)
        );
    }
}

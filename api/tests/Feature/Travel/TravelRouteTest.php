<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelRouteStop;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-206 (#6019) — Routes ville→ville et étapes ordonnées.
 *
 * Couvre l'interdiction d'une route origine==destination, l'unicité de la
 * paire origine/destination, l'ordre des étapes (`rank`) et l'absence de
 * ville dupliquée sur une même route.
 */
class TravelRouteTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->tenants = app(TenantManager::class);
    }

    public function test_route_cannot_have_same_origin_and_destination(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $city = TravelCity::factory()->create();

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRoute::factory()->create([
                'origin_city_id' => $city->id,
                'destination_city_id' => $city->id,
            ]));
        });
    }

    public function test_origin_destination_pair_is_unique_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $origin = TravelCity::factory()->create();
            $destination = TravelCity::factory()->create();

            TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]));
        });
    }

    public function test_routes_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelRoute::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelRoute::query()->count());
        });
    }

    public function test_stops_are_ordered_by_rank(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $route = TravelRoute::factory()->create();

            TravelRouteStop::factory()->create(['route_id' => $route->id, 'rank' => 2]);
            TravelRouteStop::factory()->create(['route_id' => $route->id, 'rank' => 1]);
            TravelRouteStop::factory()->create(['route_id' => $route->id, 'rank' => 3]);

            $ranks = $route->stops()->pluck('rank')->all();

            $this->assertSame([1, 2, 3], $ranks);
        });
    }

    public function test_same_city_cannot_appear_twice_on_a_route(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $route = TravelRoute::factory()->create();
            $city = TravelCity::factory()->create();

            TravelRouteStop::factory()->create(['route_id' => $route->id, 'city_id' => $city->id, 'rank' => 1]);

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRouteStop::factory()->create(['route_id' => $route->id, 'city_id' => $city->id, 'rank' => 2]));
        });
    }

    public function test_duplicate_rank_on_same_route_is_rejected(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $route = TravelRoute::factory()->create();

            TravelRouteStop::factory()->create(['route_id' => $route->id, 'rank' => 1]);

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRouteStop::factory()->create(['route_id' => $route->id, 'rank' => 1]));
        });
    }
}

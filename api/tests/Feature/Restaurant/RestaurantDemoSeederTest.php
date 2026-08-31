<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantDemoSeederService;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-107 (#6164) — Seed de démonstration idempotent.
 *
 * « 2 exécutions = même état » : le seeder démo ne crée jamais de doublons
 * (insertOrIgnore / ancrage sur clés uniques tenant-scoped), aucune donnée
 * réelle n'est mélangée (données marquées démo).
 */
class RestaurantDemoSeederTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_demo_seed_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(RestaurantDemoSeederService::class)->seed($company);
        $stateAfterFirst = $this->tenantCounts($company);

        app(RestaurantDemoSeederService::class)->seed($company);
        $stateAfterSecond = $this->tenantCounts($company);

        $this->assertSame($stateAfterFirst, $stateAfterSecond, '2 exécutions du seed démo doivent produire le même état.');
        $this->assertGreaterThan(0, $stateAfterFirst['branches']);
        $this->assertGreaterThan(0, $stateAfterFirst['products']);
    }

    public function test_demo_seed_does_not_leak_between_tenants(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(RestaurantDemoSeederService::class)->seed($companyA);

        $countInB = app(TenantManager::class)->withinTenant($companyB, function () use ($companyB): int {
            return (int) DB::table('restaurant_branches')
                ->where('company_id', $companyB->id)
                ->where('code', 'DEMO')
                ->count();
        });

        $this->assertSame(0, $countInB, 'Le seed démo du tenant A ne doit rien créer chez le tenant B.');
    }

    /**
     * @return array<string, int>
     */
    private function tenantCounts(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                'branches' => (int) DB::table('restaurant_branches')->count(),
                'zones' => (int) DB::table('restaurant_zones')->count(),
                'tables' => (int) DB::table('restaurant_tables')->count(),
                'ingredients' => (int) DB::table('restaurant_ingredients')->count(),
                'products' => (int) DB::table('restaurant_products')->count(),
                'orders' => (int) DB::table('restaurant_orders')->count(),
                'payments' => (int) DB::table('restaurant_order_payments')->count(),
            ];
        });
    }
}

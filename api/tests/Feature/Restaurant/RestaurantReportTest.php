<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-701 (#6214) — Rapports ventes/occupation/produits/COGS/caisses.
 * RESTO-703 (#6216) — Dashboard KPIs (cohérence avec les rapports).
 */
class RestaurantReportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_sales_and_kpis_are_consistent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'capacity' => 4]);

            // 2 commandes payées aujourd'hui : 10000 + 5000 = 15000.
            RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'paid', 'total_minor' => 10000, 'tax_minor' => 1900, 'discount_minor' => 0]);
            RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'paid', 'total_minor' => 5000, 'tax_minor' => 950, 'discount_minor' => 500]);

            // 2 sessions de table aujourd'hui.
            RestaurantTableSession::factory()->create(['branch_id' => $branch->id, 'table_id' => $table->id, 'covers' => 2, 'opened_at' => now()->subHours(3), 'closed_at' => now()->subHour()]);
            RestaurantTableSession::factory()->create(['branch_id' => $branch->id, 'table_id' => $table->id, 'covers' => 4, 'opened_at' => now()->subMinutes(90), 'closed_at' => null]);

            // Rapports.
            $this->getJson('/api/v1/restaurant/reports/sales')
                ->assertOk()
                ->assertJsonPath('data.report.revenue_minor', 15000)
                ->assertJsonPath('data.report.orders_count', 2)
                ->assertJsonPath('data.report.avg_basket_minor', 7500)
                ->assertJsonPath('data.report.tax_minor', 2850);

            $this->getJson('/api/v1/restaurant/reports/occupancy')
                ->assertOk()
                ->assertJsonPath('data.report.sessions_count', 2)
                ->assertJsonPath('data.report.rotation', 2.0);

            // KPIs du jour cohérents avec les rapports.
            $this->getJson('/api/v1/restaurant/dashboard/kpis')
                ->assertOk()
                ->assertJsonPath('data.revenue_minor', 15000)
                ->assertJsonPath('data.orders_count', 2)
                ->assertJsonPath('data.avg_basket_minor', 7500)
                ->assertJsonPath('data.sessions_count', 2);
        });
    }

    public function test_top_products_and_cogs(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $productA = RestaurantProduct::factory()->create(['branch_id' => $branch->id]);
            $productB = RestaurantProduct::factory()->create(['branch_id' => $branch->id]);

            $order = RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'closed']);
            RestaurantOrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $productA->id, 'quantity' => 2, 'line_total_minor' => 4000]);
            RestaurantOrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $productB->id, 'quantity' => 1, 'line_total_minor' => 1000]);

            $this->getJson('/api/v1/restaurant/reports/products')
                ->assertOk()
                ->assertJsonPath('data.report.top_products.0.product_id', $productA->id)
                ->assertJsonPath('data.report.top_products.0.revenue_minor', 4000);

            $this->getJson('/api/v1/restaurant/reports/cogs')
                ->assertOk()
                ->assertJsonPath('data.report.cogs_minor', 0);
        });
    }

    public function test_ordinary_employee_cannot_read_reports(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/restaurant/reports/sales')->assertStatus(403);
        $this->getJson('/api/v1/restaurant/dashboard/kpis')->assertStatus(403);
    }
}

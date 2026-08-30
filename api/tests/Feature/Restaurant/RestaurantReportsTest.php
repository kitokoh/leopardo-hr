<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-701/703 (#6214/#6216) — Rapports & KPIs RestaurantManager.
 *
 * Vérifie que les agrégats sont exacts par rapport aux données sous-jacentes
 * (ventes, occupation, top produits, COGS, caisses, KPIs du jour) et que la
 * permission `restaurant.reports` est exigée.
 */
class RestaurantReportsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company, string $managerRole = 'manager'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_reports_require_restaurant_reports_permission(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);

        $this->getJson('/api/v1/restaurant/reports/sales')->assertStatus(403);
        $this->getJson('/api/v1/restaurant/reports/kpis')->assertStatus(403);
        $this->postJson('/api/v1/restaurant/reports/export', ['report_type' => 'sales'])->assertStatus(403);
    }

    public function test_sales_and_occupancy_aggregates_are_exact(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        // Deux commandes payées aujourd'hui (2000 + 3000) + une draft ignorée.
        RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'total_minor' => 2000,
            'currency' => 'XAF',
        ]);
        RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'closed',
            'total_minor' => 3000,
            'currency' => 'XAF',
        ]);
        RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'draft',
            'total_minor' => 99999,
            'currency' => 'XAF',
        ]);

        /** @var RestaurantTable $table */
        $table = RestaurantTable::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        RestaurantTableSession::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'status' => 'closed',
            'covers' => 4,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
        ]);

        $this->getJson('/api/v1/restaurant/reports/sales')
            ->assertStatus(200)
            ->assertJsonPath('data.0.revenue_minor', 5000)
            ->assertJsonPath('data.0.orders', 2);

        $this->getJson('/api/v1/restaurant/reports/occupancy')
            ->assertStatus(200)
            ->assertJsonPath('data.closed_sessions', 1)
            ->assertJsonPath('data.covers', 4)
            ->assertJsonPath('data.active_tables', 1)
            ->assertJsonPath('data.rotation', 1.0);

        $this->getJson('/api/v1/restaurant/reports/kpis')
            ->assertStatus(200)
            ->assertJsonPath('data.revenue_minor', 5000)
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonPath('data.avg_basket_minor', 2500);
    }

    public function test_products_and_cogs_aggregates_are_exact(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        /** @var RestaurantProduct $product */
        $product = RestaurantProduct::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'PRD-1',
            'name' => 'Plat test',
        ]);

        /** @var RestaurantIngredient $ingredient */
        $ingredient = RestaurantIngredient::factory()->create([
            'company_id' => $company->id,
            'avg_cost_minor' => 200,
        ]);

        RestaurantProductIngredient::factory()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 1.0,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'currency' => 'XAF',
        ]);

        RestaurantOrderItem::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price_minor' => 1000,
            'line_total_minor' => 2000,
            'status' => 'active',
        ]);

        // 2 plats vendus × (1 × 200) = 400 de COGS ; chiffre 2000.
        $this->getJson('/api/v1/restaurant/reports/products')
            ->assertStatus(200)
            ->assertJsonPath('data.0.quantity', 2)
            ->assertJsonPath('data.0.revenue_minor', 2000);

        $this->getJson('/api/v1/restaurant/reports/cogs')
            ->assertStatus(200)
            ->assertJsonPath('data.total_cogs_minor', 400)
            ->assertJsonPath('data.total_revenue_minor', 2000)
            ->assertJsonPath('data.margin_minor', 1600);
    }

    public function test_pos_report_aggregates_closings(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        RestaurantPosSession::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'closed',
            'opening_cash_minor' => 5000,
            'expected_cash_minor' => 45000,
            'counted_cash_minor' => 44900,
            'variance_minor' => -100,
            'opened_at' => now()->subHours(6),
            'closed_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/restaurant/reports/pos')
            ->assertStatus(200)
            ->assertJsonPath('data.closings', 1)
            ->assertJsonPath('data.opening_cash_minor', 5000)
            ->assertJsonPath('data.expected_cash_minor', 45000)
            ->assertJsonPath('data.counted_cash_minor', 44900)
            ->assertJsonPath('data.variance_minor', -100);
    }
}

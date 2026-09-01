<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-803 (#6224) — App mobile gérant : KPIs, alertes stock, clôtures.
 *
 * Couvre : KPIs du jour calculés côté serveur (CA, commandes, panier moyen),
 * alertes de seuil de stock, session de caisse courante et clôture avec
 * écart calculé serveur.
 */
class RestaurantMobileManagerApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
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

    public function test_manager_kpis_are_computed_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'paid',
                'currency' => 'XAF',
                'total_minor' => 4000,
            ]);
        });

        $this->getJson('/api/v1/restaurant/mobile/manager/kpis')
            ->assertOk()
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.today_revenue_minor', 4000)
            ->assertJsonPath('data.avg_basket_minor', 4000);
    }

    public function test_manager_sees_stock_alerts(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $branch = RestaurantBranch::factory()->create();
            RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'quantity' => 2.0,
                'alert_threshold' => 5.0,
            ]);
        });

        $this->getJson('/api/v1/restaurant/mobile/manager/stock-alerts')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_closes_pos_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $session = app(TenantManager::class)->withinTenant($company, function (): RestaurantPosSession {
            return RestaurantPosSession::factory()->create(['opening_cash_minor' => 1000]);
        });

        $this->getJson('/api/v1/restaurant/mobile/manager/pos-sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);

        $this->postJson('/api/v1/restaurant/mobile/manager/pos-sessions/'.$session->id.'/close', [
            'counted_cash_minor' => 900,
            'variance_reason' => 'écart caisse',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.variance_minor', -100);
    }
}

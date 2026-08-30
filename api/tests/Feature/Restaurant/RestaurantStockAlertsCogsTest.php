<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-505 (#6204) — Alertes de seuil + événement `restaurant.stock.alert.v1`.
 * RESTO-506 (#6205) — COGS : calcul serveur (quantités × coût moyen).
 */
class RestaurantStockAlertsCogsTest extends TestCase
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

    public function test_stock_alert_event_is_deduplicated_per_day(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 2,
                'alert_threshold' => 5,
            ]);

            // Premier scan → alerte créée.
            $this->artisan('leopardo:restaurant:stock-alerts', ['company' => $company->id])
                ->assertSuccessful();

            $this->assertSame(1, RestaurantOutboxEvent::query()
                ->where('event_type', 'restaurant.stock.alert.v1')
                ->count());

            // Second scan (rejeu) → dédup : toujours une seule alerte (pas de spam).
            $this->artisan('leopardo:restaurant:stock-alerts', ['company' => $company->id])
                ->assertSuccessful();

            $this->assertSame(1, RestaurantOutboxEvent::query()
                ->where('event_type', 'restaurant.stock.alert.v1')
                ->count());

            // Endpoint lecture des alertes.
            $this->getJson('/api/v1/restaurant/stock/alerts')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    }

    public function test_cogs_is_computed_server_side_and_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);

            // Ingrédient à coût moyen 300 (minor units) ; produit = 2 unités d'ingrédient.
            $ingredient = RestaurantIngredient::factory()->create();
            RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 50,
                'avg_cost_minor' => 300,
            ]);

            $product = RestaurantProduct::factory()->create(['branch_id' => $branch->id]);
            RestaurantProductIngredient::factory()->create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 2,
            ]);

            $session = RestaurantPosSession::factory()->create(['branch_id' => $branch->id]);

            // Commande confirmée : 3 × produit → COGS = 3 × (2 × 300) = 1800.
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'pos_session_id' => $session->id,
                'status' => 'paid',
            ]);
            RestaurantOrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 3,
                'status' => 'active',
            ]);

            $response = $this->getJson("/api/v1/restaurant/pos-sessions/{$session->id}/cogs")
                ->assertOk()
                ->assertJsonPath('data.cogs_minor', 1800)
                ->assertJsonPath('data.orders_count', 1);

            // Idempotence : recalculer donne le même résultat (critère d'acceptation).
            $this->getJson("/api/v1/restaurant/pos-sessions/{$session->id}/cogs")
                ->assertOk()
                ->assertJsonPath('data.cogs_minor', $response->json('data.cogs_minor'));
        });
    }
}

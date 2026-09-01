<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-901 (#6230) — Golden journey GJ-RESTO-01 (flux roi, MAT-013).
 *
 * Parcours complet « service en salle » en conditions réelles d'API :
 *   ouverture de caisse → commande → article → soumission → encaissement →
 *   clôture de caisse. Vérifie la machine à états, les totaux serveur et les
 *   événements outbox publiés — enregistré dans
 *   dev-hub/tools/golden-journeys.json (garde CI check-golden-journeys.sh).
 */
class RestaurantGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeServer(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_gj_resto_01_full_table_service_flow(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();
        $this->makeServer($company);

        [$branch, $table, $product] = app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id]);
            $category = RestaurantCategory::factory()->create(['name' => 'Plats']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => null,
                'category_id' => $category->id,
                'price_minor' => 3000,
                'currency' => 'XAF',
                'is_available' => true,
                'status' => 'active',
                'tax_rate_id' => null,
            ]);

            return [$branch, $table, $product];
        });

        // 1. Ouverture d'une session de caisse.
        $session = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 0,
        ])->assertStatus(201)->json('data');

        // 2. Création de la commande (salle).
        $order = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'pos_session_id' => $session['id'],
            'covers' => 2,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        // 3. Ajout d'un article (prix serveur).
        $this->postJson("/api/v1/restaurant/orders/{$order['id']}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(201);

        // 4. Soumission de la commande.
        $this->postJson("/api/v1/restaurant/orders/{$order['id']}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        // 5. Encaissement espèces (montant serveur = total calculé).
        $paid = $this->postJson("/api/v1/restaurant/orders/{$order['id']}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 6000,
        ])->assertStatus(201)->json('data');
        $this->assertSame('confirmed', $paid['status']);

        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order['id'],
            'status' => 'paid',
            'total_minor' => 6000,
        ]);

        // 6. Clôture de la caisse (comptage exact — aucun écart).
        $this->postJson("/api/v1/restaurant/pos-sessions/{$session['id']}/close", [
            'counted_cash_minor' => 6000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed');

        // Événements outbox du flux roi publiés (file cuisine, accounting…).
        $this->assertDatabaseHas('restaurant_outbox_events', ['event_type' => 'restaurant.order.paid.v1']);
        $this->assertDatabaseHas('restaurant_outbox_events', ['event_type' => 'restaurant.pos.closed.v1']);
    }

    public function test_gj_resto_01_rejects_cross_tenant_order_access(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyA->setFeature('restaurantmanager', true);
        $companyA->save();
        $this->makeServer($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyB->setFeature('restaurantmanager', true);
        $companyB->save();

        [$branch, , $product] = app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): array {
            $branch = RestaurantBranch::factory()->create();
            $category = RestaurantCategory::factory()->create(['name' => 'Plats']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => null,
                'category_id' => $category->id,
                'price_minor' => 1000,
                'currency' => 'XAF',
                'is_available' => true,
                'status' => 'active',
                'tax_rate_id' => null,
            ]);

            return [$branch, null, $product];
        });

        $order = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'takeaway',
        ])->assertStatus(201)->json('data');

        // L'employé du tenant B ne peut ni voir ni payer la commande de A.
        /** @var Employee $serverB */
        $serverB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);
        Sanctum::actingAs($serverB);

        $this->getJson("/api/v1/restaurant/orders/{$order['id']}")->assertStatus(404);

        $this->postJson("/api/v1/restaurant/orders/{$order['id']}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1000,
        ])->assertStatus(404);
    }
}

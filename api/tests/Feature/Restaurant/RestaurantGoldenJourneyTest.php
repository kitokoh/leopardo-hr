<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-901 (#6230) — Golden journey GJ-RESTO-01 : service en salle complet
 * (spec §4.1, MAT-013).
 *
 * Flux roi de bout en bout via l'API : ouverture de caisse → ouverture de
 * table → création de commande dine_in → article → soumission → cuisine
 * (start/ready) → service → addition → encaissement espèces → clôture de
 * table → clôture de caisse. Le parcours est enregistré dans
 * `dev-hub/tools/golden-journeys.json` (garde check-golden-journeys.sh).
 */
class RestaurantGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function server(Company $company): Employee
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

    private function kitchen(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

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

    public function test_gj_resto_01_full_dine_in_flow(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        $this->server($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id, 'currency' => 'XAF']);

        /** @var RestaurantTable $table */
        $table = RestaurantTable::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);

        /** @var RestaurantProduct $product */
        $product = RestaurantProduct::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'price_minor' => 1500,
            'currency' => 'XAF',
            'is_available' => true,
        ]);

        // 1. Ouverture de caisse.
        $session = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 5000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'open');
        $sessionId = $session->json('data.id');

        // 2. Ouverture de table.
        $this->postJson("/api/v1/restaurant/tables/{$table->id}/open", ['covers' => 4])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open');

        // 3. Création de commande dine_in.
        $order = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'covers' => 4,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
        $orderId = $order->json('data.id');

        // 4. Ajout d'article (prix serveur).
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(201);

        // 5. Soumission.
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open');

        // 6. Confirmation (départ cuisine).
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation');

        // 7-8. Cuisine : start puis ready.
        $this->kitchen($company);
        $this->postJson("/api/v1/restaurant/kitchen/orders/{$orderId}/start")
            ->assertStatus(200);
        $this->postJson("/api/v1/restaurant/kitchen/orders/{$orderId}/ready")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        // 9. Service.
        $this->server($company);
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/serve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'served');

        // 10. Addition (totaux serveur).
        $bill = $this->getJson("/api/v1/restaurant/orders/{$orderId}/bill")
            ->assertStatus(200);
        $total = $bill->json('data.total_minor');
        $this->assertSame(3000, $total, '2 × 1500 minor units.');

        // 11. Encaissement espèces (montant vérifié serveur).
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => $total,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        // 12. Clôture de table.
        $this->postJson("/api/v1/restaurant/tables/{$table->id}/close")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // 13. Clôture de caisse (écart calculé serveur).
        $this->principal($company);
        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 5000 + $total,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.variance_minor', 0);
    }
}

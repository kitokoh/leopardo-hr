<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-502 (#6201) — Bons de commande fournisseurs (draft/sent/receive).
 *
 * Couvre : CRUD + lignes (total recalculé serveur), transitions
 * send/receive/cancel (hors workflow → 409), réception → mouvements de stock
 * générés (critère d'acceptation) et coût moyen mis à jour.
 */
class RestaurantPurchaseOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{branch: RestaurantBranch, supplier: RestaurantSupplier, ingredient: RestaurantIngredient}
     */
    private function makePurchaseContext(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $supplier = RestaurantSupplier::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            return ['branch' => $branch, 'supplier' => $supplier, 'ingredient' => $ingredient];
        });
    }

    public function test_purchase_order_flow_draft_send_receive(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'supplier' => $supplier, 'ingredient' => $ingredient] = $this->makePurchaseContext($company);

        $poId = $this->postJson('/api/v1/restaurant/purchase-orders', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        // Lignes : 10 kg × 500 + 5 kg × 300 = 6500.
        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/items", [
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'unit_price_minor' => 500,
        ])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/items", [
            'ingredient_id' => $ingredient->id,
            'quantity' => 5,
            'unit_price_minor' => 300,
        ])->assertStatus(201);

        $po = app(TenantManager::class)->withinTenant($company, fn (): RestaurantPurchaseOrder => RestaurantPurchaseOrder::query()->findOrFail($poId));
        $this->assertSame(6500, (int) $po->total_minor);

        // send : draft → sent.
        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/send")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        // receive : sent → received → mouvements de stock générés.
        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/receive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(15, (float) $level->quantity, 0.001);
        // Coût moyen pondéré : (10×500 + 5×300) / 15 = 433.33 → arrondi 433.
        $this->assertSame(433, (int) $level->avg_cost_minor);

        $movements = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement::query()
            ->where('reason_code', 'receiving')
            ->count());

        $this->assertSame(2, $movements);
    }

    public function test_receive_on_draft_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'supplier' => $supplier] = $this->makePurchaseContext($company);

        $poId = $this->postJson('/api/v1/restaurant/purchase-orders', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/receive")->assertStatus(409);
    }

    public function test_receive_generates_receiving_document(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'supplier' => $supplier, 'ingredient' => $ingredient] = $this->makePurchaseContext($company);

        $poId = $this->postJson('/api/v1/restaurant/purchase-orders', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/items", [
            'ingredient_id' => $ingredient->id,
            'quantity' => 4,
            'unit_price_minor' => 250,
        ])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/send")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/purchase-orders/{$poId}/receive")->assertStatus(200);

        $receiving = app(TenantManager::class)->withinTenant($company, fn () => \App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving::query()
            ->where('purchase_order_id', $poId)
            ->first());

        $this->assertNotNull($receiving);
        $this->assertStringStartsWith('RCV-', $receiving->reference);
    }
}

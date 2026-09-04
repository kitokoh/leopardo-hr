<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-502 (#6201), RESTO-503 (#6202), RESTO-504 (#6203) — Achats, réceptions
 * et inventaires physiques.
 *
 * Couvre les transitions des bons de commande (draft → sent → received),
 * le coût moyen pondéré recalculé à la réception (résultat exact), les
 * ajustements d'inventaire approuvés et le blocage d'une approbation avec
 * écart non justifié.
 */
class RestaurantPurchaseReceivingInventoryTest extends TestCase
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

    public function test_purchase_order_lifecycle_draft_sent_received(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $ids = app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $supplier = RestaurantSupplier::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            $this->postJson('/api/v1/restaurant/purchase-orders', [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'items' => [
                    ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit_price_minor' => 500],
                    ['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_price_minor' => 1000],
                ],
            ])->assertStatus(201)
                ->assertJsonPath('data.status', PurchaseOrderStatus::DRAFT->value)
                ->assertJsonPath('data.total_minor', 10000);

            /** @var RestaurantPurchaseOrder $order */
            $order = RestaurantPurchaseOrder::query()->firstOrFail();

            $this->postJson("/api/v1/restaurant/purchase-orders/{$order->id}/send")
                ->assertOk()
                ->assertJsonPath('data.status', PurchaseOrderStatus::SENT->value);

            $this->postJson("/api/v1/restaurant/purchase-orders/{$order->id}/receive", [
                'items' => [
                    ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit_price_minor' => 500],
                    ['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_price_minor' => 1000],
                ],
            ])->assertOk()
                ->assertJsonPath('data.status', PurchaseOrderStatus::RECEIVED->value);

            $this->assertSame(2, RestaurantInventoryMovement::query()
                ->where('reference_type', 'purchase_order')
                ->where('reference_id', $order->id)
                ->count());

            $this->assertSame('15.000', (string) RestaurantStockLevel::query()
                ->where('ingredient_id', $ingredient->id)
                ->firstOrFail()->quantity);

            return ['order' => $order->id];
        });
    }

    public function test_receiving_recomputes_weighted_average_cost(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            // Stock initial : 10 kg à 200 (coût moyen = 200).
            $level = RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 10,
                'avg_cost_minor' => 200,
            ]);

            // Réception : +10 kg à 400 → coût moyen = (10×200 + 10×400)/20 = 300.
            $this->postJson('/api/v1/restaurant/receivings', [
                'branch_id' => $branch->id,
                'items' => [
                    ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit_price_minor' => 400],
                ],
            ])->assertStatus(201);

            $this->assertSame('20.000', (string) $level->refresh()->quantity);
            $this->assertSame(300, $level->avg_cost_minor);
        });
    }

    public function test_inventory_count_approval_applies_adjustments(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            $level = RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 10,
            ]);

            // Création : la ligne attendue est pré-remplie (10).
            $this->postJson('/api/v1/restaurant/inventory-counts', ['branch_id' => $branch->id])
                ->assertStatus(201)
                ->assertJsonPath('data.items.0.expected_qty', '10.000');

            $countId = RestaurantInventoryCount::query()->firstOrFail()->id;
            $itemId = RestaurantInventoryCountItem::query()->firstOrFail()->id;

            // Saisie : 8 comptés → écart -2 justifié.
            $this->putJson("/api/v1/restaurant/inventory-counts/{$countId}/items/{$itemId}", [
                'counted_qty' => 8,
                'reason_code' => 'spoilage',
            ])->assertOk();

            $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/submit")->assertOk();

            $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/approve")
                ->assertOk()
                ->assertJsonPath('data.status', 'approved');

            // Stock ajusté à 8 + mouvement raison count.
            $this->assertSame('8.000', (string) $level->refresh()->quantity);
            $this->assertSame(1, RestaurantInventoryMovement::query()
                ->where('reason_code', 'count')
                ->where('reference_id', $countId)
                ->count());
        });
    }

    public function test_inventory_count_approval_blocked_without_justification(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            RestaurantStockLevel::factory()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 10,
            ]);

            $this->postJson('/api/v1/restaurant/inventory-counts', ['branch_id' => $branch->id])->assertStatus(201);

            $countId = RestaurantInventoryCount::query()->firstOrFail()->id;
            $itemId = RestaurantInventoryCountItem::query()->firstOrFail()->id;

            // Écart SANS justification (raison absente) → approbation bloquée.
            $this->putJson("/api/v1/restaurant/inventory-counts/{$countId}/items/{$itemId}", ['counted_qty' => 7])
                ->assertOk();

            $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/submit")->assertOk();

            $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/approve")
                ->assertStatus(422);
        });
    }
}

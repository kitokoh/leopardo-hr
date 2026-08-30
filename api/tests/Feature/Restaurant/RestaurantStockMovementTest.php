<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-501 (#6200) — Niveaux de stock & mouvements (raisons, références).
 *
 * Couvre : ajustement appliqué (quantité + journal), stock jamais négatif
 * (422), seuils modifiables mais quantité non écrite directement
 * (`quantity` prohibé), raisons bornées et isolation cross-tenant (404 sûr).
 */
class RestaurantStockMovementTest extends TestCase
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
     * @return array{branch: RestaurantBranch, ingredient: RestaurantIngredient}
     */
    private function makeBranchIngredient(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            return ['branch' => $branch, 'ingredient' => $ingredient];
        });
    }

    public function test_adjustment_movement_updates_stock_and_journal(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        $this->postJson('/api/v1/restaurant/inventory-movements', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_delta' => 12.5,
            'reason_code' => 'adjustment',
            'note_redacted' => 'Réception directe non référencée',
        ])->assertStatus(201)
            ->assertJsonPath('data.quantity_delta', 12.5)
            ->assertJsonPath('data.reason_code', 'adjustment');

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(12.5, (float) $level->quantity, 0.001);

        // Journal tracé avec la raison et la note.
        $movement = app(TenantManager::class)->withinTenant($company, fn () => \App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->first());

        $this->assertNotNull($movement);
        $this->assertSame('adjustment', $movement->reason_code->value);
        $this->assertSame('Réception directe non référencée', $movement->note_redacted);
    }

    public function test_stock_never_goes_negative(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        // Stock initial : 5.
        app(TenantManager::class)->withinTenant($company, fn () => RestaurantStockLevel::query()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 5,
        ]));

        // Perte de 10 → refusé (422), jamais négatif.
        $this->postJson('/api/v1/restaurant/inventory-movements', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_delta' => -10,
            'reason_code' => 'waste',
        ])->assertStatus(422);

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(5, (float) $level->quantity, 0.001);
    }

    public function test_quantity_cannot_be_written_directly_on_level(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        $levelId = app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantStockLevel::query()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 5,
        ])->id);

        $this->putJson("/api/v1/restaurant/stock-levels/{$levelId}", [
            'quantity' => 999,
            'reorder_level' => 3,
        ])->assertStatus(422); // quantity est prohibé

        $this->putJson("/api/v1/restaurant/stock-levels/{$levelId}", [
            'reorder_level' => 3,
            'alert_threshold' => 2,
        ])->assertStatus(200)
            ->assertJsonPath('data.reorder_level', 3.0)
            ->assertJsonPath('data.alert_threshold', 2.0);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        $this->postJson('/api/v1/restaurant/inventory-movements', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_delta' => 5,
            'reason_code' => 'sale', // réservé aux flux métier
        ])->assertStatus(422);
    }

    public function test_other_tenant_level_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $otherLevelId = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): int => RestaurantStockLevel::factory()->create()->id
        );

        $this->putJson("/api/v1/restaurant/stock-levels/{$otherLevelId}", ['reorder_level' => 1])->assertStatus(404);
    }
}

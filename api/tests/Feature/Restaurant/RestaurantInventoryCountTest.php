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
 * RESTO-504 (#6203) — Inventaires physiques (comptage, écarts justifiés,
 * approbation).
 *
 * Couvre : création avec lignes attendues pré-remplies, saisie du compté
 * (variance calculée serveur), écart non justifié → approbation bloquée
 * (422 — critère d'acceptation), approbation → ajustements de stock
 * (mouvements `count`).
 */
class RestaurantInventoryCountTest extends TestCase
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
     * Crée branche + ingrédient avec stock attendu 10.
     *
     * @return array{branch: RestaurantBranch, ingredient: RestaurantIngredient}
     */
    private function makeStockedBranch(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            RestaurantStockLevel::query()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 10,
            ]);

            return ['branch' => $branch, 'ingredient' => $ingredient];
        });
    }

    public function test_create_prefills_expected_lines_and_approval_applies_adjustments(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeStockedBranch($company);

        $countId = $this->postJson('/api/v1/restaurant/inventory-counts', [
            'branch_id' => $branch->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        // Ligne attendue pré-remplie (10).
        $itemId = $this->getJson("/api/v1/restaurant/inventory-counts/{$countId}")
            ->assertStatus(200)
            ->json('data.items.0.id');
        $this->assertNotNull($itemId);

        // Comptage : 8 (écart −2 justifié).
        $this->putJson("/api/v1/restaurant/inventory-counts/{$countId}/items/{$itemId}", [
            'counted_qty' => 8,
            'reason_code' => 'loss',
        ])->assertStatus(200)
            ->assertJsonPath('data.variance_qty', -2);

        $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/submit")->assertStatus(200)->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Stock ajusté à 8 (mouvement count).
        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(8, (float) $level->quantity, 0.001);

        $countMovements = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement::query()
            ->where('reason_code', 'count')
            ->count());

        $this->assertSame(1, $countMovements);
    }

    public function test_approval_blocked_on_unjustified_variance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        ['branch' => $branch] = $this->makeStockedBranch($company);

        $countId = $this->postJson('/api/v1/restaurant/inventory-counts', ['branch_id' => $branch->id])
            ->assertStatus(201)->json('data.id');

        $itemId = $this->getJson("/api/v1/restaurant/inventory-counts/{$countId}")->json('data.items.0.id');

        // Comptage 8 SANS motif → écart non justifié.
        $this->putJson("/api/v1/restaurant/inventory-counts/{$countId}/items/{$itemId}", ['counted_qty' => 8])
            ->assertStatus(200)
            ->assertJsonPath('data.variance_qty', -2);

        $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/submit")->assertStatus(200);

        $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/approve")->assertStatus(422);
    }

    public function test_manager_cannot_approve_inventory_count(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch] = $this->makeStockedBranch($company);

        $countId = $this->postJson('/api/v1/restaurant/inventory-counts', ['branch_id' => $branch->id])
            ->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/inventory-counts/{$countId}/approve")->assertStatus(403);
    }
}

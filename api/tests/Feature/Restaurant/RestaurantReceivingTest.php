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
 * RESTO-503 (#6202) — Réceptions (entrées stock, coût moyen pondéré).
 *
 * Couvre : réception directe (lignes), recalcul EXACT du coût moyen pondéré
 * (critère d'acceptation), idempotence par référence client (rejeu → 409).
 */
class RestaurantReceivingTest extends TestCase
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

    public function test_weighted_average_cost_is_recomputed_exactly(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        // Stock initial : 10 kg à 1000.
        app(TenantManager::class)->withinTenant($company, fn () => RestaurantStockLevel::query()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'avg_cost_minor' => 1000,
        ]));

        // Réception de 10 kg à 2000 → moyenne = (10×1000 + 10×2000)/20 = 1500.
        $this->postJson('/api/v1/restaurant/receivings', [
            'branch_id' => $branch->id,
            'reference' => 'RCV-TEST-001',
            'lines' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit_price_minor' => 2000],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.reference', 'RCV-TEST-001');

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(20, (float) $level->quantity, 0.001);
        $this->assertSame(1500, (int) $level->avg_cost_minor);
    }

    public function test_receiving_without_previous_cost_uses_unit_price(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        $this->postJson('/api/v1/restaurant/receivings', [
            'branch_id' => $branch->id,
            'lines' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 25, 'unit_price_minor' => 800],
            ],
        ])->assertStatus(201);

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(25, (float) $level->quantity, 0.001);
        $this->assertSame(800, (int) $level->avg_cost_minor);
    }

    public function test_receiving_replay_with_same_reference_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);
        ['branch' => $branch, 'ingredient' => $ingredient] = $this->makeBranchIngredient($company);

        $payload = [
            'branch_id' => $branch->id,
            'reference' => 'RCV-TEST-002',
            'lines' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_price_minor' => 100],
            ],
        ];

        $this->postJson('/api/v1/restaurant/receivings', $payload)->assertStatus(201);

        // Rejeu de la même référence → 409, pas de double entrée de stock.
        $this->postJson('/api/v1/restaurant/receivings', $payload)->assertStatus(409);

        $level = app(TenantManager::class)->withinTenant($company, fn (): RestaurantStockLevel => RestaurantStockLevel::query()
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail());

        $this->assertEqualsWithDelta(5, (float) $level->quantity, 0.001);
    }
}

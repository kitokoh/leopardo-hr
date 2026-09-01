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
 * RESTO-505 (#6204) — Alertes de seuil + événement restaurant.stock.alert.v1.
 *
 * Couvre : seuil franchi après mouvement → événement unique par
 * (branche, ingrédient, jour) — le rescan ne spamme pas (critère
 * d'acceptation « une alerte par ingrédient/période »).
 */
class RestaurantStockAlertTest extends TestCase
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

    public function test_threshold_crossed_publishes_single_alert_per_day(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $levelId = app(TenantManager::class)->withinTenant($company, function (): int {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            $level = RestaurantStockLevel::query()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 12,
                'alert_threshold' => 10,
            ]);

            return $level->id;
        });

        // Mouvement qui fait passer sous le seuil (12 → 8).
        $this->postJson('/api/v1/restaurant/inventory-movements', [
            'branch_id' => app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantStockLevel::query()->findOrFail($levelId)->branch_id),
            'ingredient_id' => app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantStockLevel::query()->findOrFail($levelId)->ingredient_id),
            'quantity_delta' => -4,
            'reason_code' => 'waste',
        ])->assertStatus(201);

        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.stock.alert.v1')
            ->count());

        $this->assertSame(1, $eventCount);

        // Rescan du même jour : la clé d'idempotence (jour) déduplique.
        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            app(\App\Modules\RestaurantManager\Application\Services\StockAlertService::class)->scan($company->id);
        });

        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.stock.alert.v1')
            ->count());

        $this->assertSame(1, $eventCount);
    }

    public function test_no_alert_when_above_threshold(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $levelId = app(TenantManager::class)->withinTenant($company, function (): int {
            $branch = RestaurantBranch::factory()->create();
            $ingredient = RestaurantIngredient::factory()->create();

            return RestaurantStockLevel::query()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 20,
                'alert_threshold' => 10,
            ])->id;
        });

        $this->postJson('/api/v1/restaurant/inventory-movements', [
            'branch_id' => app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantStockLevel::query()->findOrFail($levelId)->branch_id),
            'ingredient_id' => app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantStockLevel::query()->findOrFail($levelId)->ingredient_id),
            'quantity_delta' => -2,
            'reason_code' => 'waste',
        ])->assertStatus(201);

        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.stock.alert.v1')
            ->count());

        $this->assertSame(0, $eventCount);
    }
}

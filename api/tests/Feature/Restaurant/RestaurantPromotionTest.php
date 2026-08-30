<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-607 (#6212) — Promotions (types, bornes, cumul, codes).
 *
 * Couvre le calcul percent/amount, les bornes (période, minimum de commande,
 * plafond d'utilisation) et le refus d'une promo expirée/épuisée.
 */
class RestaurantPromotionTest extends TestCase
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

    public function test_percent_promotion_computed_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantPromotion::factory()->create([
                'code' => 'HAPPY10',
                'discount_type' => 'percent',
                'value_minor' => 1000, // 10 % en points de base
                'min_order_minor' => 5000,
                'max_uses' => 10,
            ]);

            // Sous le minimum → 422.
            $this->postJson('/api/v1/restaurant/promotions/validate', ['code' => 'HAPPY10', 'order_total_minor' => 3000])
                ->assertStatus(422);

            // 10 % de 10000 = 1000.
            $this->postJson('/api/v1/restaurant/promotions/validate', ['code' => 'HAPPY10', 'order_total_minor' => 10000])
                ->assertOk()
                ->assertJsonPath('data.discount_minor', 1000)
                ->assertJsonPath('data.valid', true);
        });
    }

    public function test_expired_and_exhausted_promotions_refused(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantPromotion::factory()->create([
                'code' => 'OLD',
                'discount_type' => 'amount',
                'value_minor' => 500,
                'ends_at' => Carbon::yesterday(),
            ]);

            $this->postJson('/api/v1/restaurant/promotions/validate', ['code' => 'OLD', 'order_total_minor' => 10000])
                ->assertStatus(422);

            RestaurantPromotion::factory()->create([
                'code' => 'FULL',
                'discount_type' => 'amount',
                'value_minor' => 500,
                'max_uses' => 1,
                'used_count' => 1,
            ]);

            $this->postJson('/api/v1/restaurant/promotions/validate', ['code' => 'FULL', 'order_total_minor' => 10000])
                ->assertStatus(422);
        });
    }

    public function test_unknown_code_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/promotions/validate', ['code' => 'NOPE', 'order_total_minor' => 10000])
            ->assertStatus(404);
    }
}

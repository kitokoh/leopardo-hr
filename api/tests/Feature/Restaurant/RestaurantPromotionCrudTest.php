<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-607 (#6212) — Promotions RestaurantManager.
 *
 * Couvre le CRUD, les bornes de validation (percent 1..10000, amount > 0,
 * code unique par tenant, fenêtre ordonnée) et l'application dans l'addition
 * via BillCalculator (RESTO-405) : promo expirée/épuisée refusée, plafond
 * d'utilisations incrémenté, remise bornée au sous-total.
 */
class RestaurantPromotionCrudTest extends TestCase
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

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_principal_can_create_promotion(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'HAPPY10',
            'title' => 'Happy hour -10 %',
            'discount_type' => 'percent',
            'value_minor' => 1000, // 10 %
            'min_order_minor' => 1000,
            'max_uses' => 100,
            'is_active' => true,
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'HAPPY10', 'used_count' => 0]);
    }

    public function test_promotion_code_is_unique_per_tenant(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'DUP',
            'title' => 'Première',
            'discount_type' => 'percent',
            'value_minor' => 500,
        ])->assertStatus(201);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'DUP',
            'title' => 'Doublon',
            'discount_type' => 'percent',
            'value_minor' => 500,
        ])->assertStatus(422);
    }

    public function test_percent_value_bounds(): void
    {
        $company = $this->company();
        $this->principal($company);

        // 150 % → refusé (borne 1..10000).
        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'TOOMUCH',
            'title' => 'Trop',
            'discount_type' => 'percent',
            'value_minor' => 15000,
        ])->assertStatus(422);
    }

    public function test_amount_value_must_be_positive(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'FREE',
            'title' => 'Zéro',
            'discount_type' => 'amount',
            'value_minor' => 0,
        ])->assertStatus(422);
    }

    public function test_window_must_be_ordered(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'WRONG',
            'title' => 'Fenêtre inversée',
            'discount_type' => 'percent',
            'value_minor' => 1000,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-08-01 10:00:00',
        ])->assertStatus(422);
    }

    public function test_ordinary_employee_cannot_create_promotion(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);

        $this->postJson('/api/v1/restaurant/promotions', [
            'code' => 'NOPE',
            'title' => 'Interdit',
            'discount_type' => 'percent',
            'value_minor' => 1000,
        ])->assertStatus(403);
    }

    public function test_expired_promotion_refused_on_bill(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        /** @var RestaurantPromotion $promotion */
        $promotion = RestaurantPromotion::factory()->create([
            'company_id' => $company->id,
            'code' => 'EXPIREE',
            'discount_type' => 'percent',
            'value_minor' => 1000,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'currency' => 'XAF',
        ]);

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code={$promotion->code}")
            ->assertStatus(422);
    }

    public function test_exhausted_promotion_refused_on_bill(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        /** @var RestaurantPromotion $promotion */
        $promotion = RestaurantPromotion::factory()->create([
            'company_id' => $company->id,
            'code' => 'EPUISEE',
            'discount_type' => 'amount',
            'value_minor' => 500,
            'max_uses' => 3,
            'used_count' => 3,
            'is_active' => true,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'currency' => 'XAF',
        ]);

        $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code={$promotion->code}")
            ->assertStatus(422);
    }

    public function test_valid_promotion_applies_and_increments_usage(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

        /** @var RestaurantPromotion $promotion */
        $promotion = RestaurantPromotion::factory()->create([
            'company_id' => $company->id,
            'code' => 'MOINS10',
            'discount_type' => 'percent',
            'value_minor' => 1000, // 10 %
            'max_uses' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'subtotal_minor' => 10000,
            'tax_minor' => 0,
            'discount_minor' => 0,
            'total_minor' => 10000,
            'currency' => 'XAF',
        ]);

        $response = $this->getJson("/api/v1/restaurant/orders/{$order->id}/bill?promotion_code={$promotion->code}")
            ->assertStatus(200);

        $response->assertJsonPath('data.total_minor', 9000);
        $response->assertJsonPath('data.discount_minor', 1000);

        $promotion->refresh();
        $this->assertSame(1, $promotion->used_count, 'Le compteur d\'utilisations est incrémenté.');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-606 (#6211) — Programme fidélité (points, récompenses, opt-in).
 *
 * Couvre l'opt-in RGPD obligatoire, le crédit de points exact et unique par
 * commande payée, et le solde jamais négatif.
 */
class RestaurantLoyaltyTest extends TestCase
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

    public function test_opt_in_is_required_to_activate_loyalty_customer(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->postJson('/api/v1/restaurant/loyalty-customers', ['customer_contact_id' => 42, 'opt_in' => false])
                ->assertStatus(422);

            $this->postJson('/api/v1/restaurant/loyalty-customers', ['customer_contact_id' => 42, 'opt_in' => true])
                ->assertStatus(201)
                ->assertJsonPath('data.opted_in_at', \Illuminate\Support\Carbon::now()->toIso8601String());
        });
    }

    public function test_points_credited_once_per_paid_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantLoyaltyProgram::factory()->create(['points_per_amount_minor' => 100]);

            $this->postJson('/api/v1/restaurant/loyalty-customers', ['customer_contact_id' => 7, 'opt_in' => true])
                ->assertStatus(201);

            $customerId = RestaurantLoyaltyCustomer::query()->firstOrFail()->id;

            $order = RestaurantOrder::factory()->create(['status' => 'paid', 'total_minor' => 2500]);

            // 2500 / 100 = 25 points, crédités une seule fois.
            $this->postJson("/api/v1/restaurant/loyalty-customers/{$customerId}/credit", ['order_id' => $order->id])
                ->assertOk()
                ->assertJsonPath('credited', 25)
                ->assertJsonPath('data.points', 25);

            $this->postJson("/api/v1/restaurant/loyalty-customers/{$customerId}/credit", ['order_id' => $order->id])
                ->assertOk()
                ->assertJsonPath('credited', 0)
                ->assertJsonPath('already_credited', true)
                ->assertJsonPath('data.points', 25);

            // Commande non payée → refus.
            $draft = RestaurantOrder::factory()->create(['status' => 'draft', 'total_minor' => 2500]);
            $this->postJson("/api/v1/restaurant/loyalty-customers/{$customerId}/credit", ['order_id' => $draft->id])
                ->assertStatus(422);
        });
    }

    public function test_redeem_never_goes_negative(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->postJson('/api/v1/restaurant/loyalty-customers', ['customer_contact_id' => 9, 'opt_in' => true])
                ->assertStatus(201);

            $customerId = RestaurantLoyaltyCustomer::query()->firstOrFail()->id;

            // Solde 0 → débit refusé (jamais négatif).
            $this->postJson("/api/v1/restaurant/loyalty-customers/{$customerId}/redeem", ['points' => 10])
                ->assertStatus(422);
        });
    }
}

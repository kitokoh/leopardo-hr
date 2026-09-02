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
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;

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


    public function test_principal_can_create_and_list_program(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/loyalty-programs', [
            'points_per_amount_minor' => 100,
            'redeem_rate_minor' => 100,
            'is_active' => true,
        ])->assertStatus(201)
            ->assertJsonFragment(['points_per_amount_minor' => 100, 'is_active' => true]);

        $this->getJson('/api/v1/restaurant/loyalty-programs')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }


    public function test_only_one_active_program(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/restaurant/loyalty-programs', ['points_per_amount_minor' => 100])->assertStatus(201);
        $this->postJson('/api/v1/restaurant/loyalty-programs', ['points_per_amount_minor' => 50])->assertStatus(201);

        $this->assertSame(
            1,
            RestaurantLoyaltyProgram::query()->where('is_active', true)->count(),
            'Un seul programme actif par tenant.'
        );
    }


    public function test_ordinary_employee_cannot_configure_program(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);

        $this->postJson('/api/v1/restaurant/loyalty-programs', ['points_per_amount_minor' => 100])
            ->assertStatus(403);
    }


    public function test_opt_in_creates_customer_account(): void
    {
        $company = $this->company();
        $this->manager($company);

        $this->postJson('/api/v1/restaurant/loyalty-customers', [
            'customer_contact_id' => 4242,
        ])->assertStatus(201)
            ->assertJsonFragment(['customer_contact_id' => 4242, 'points' => 0]);
    }


    public function test_points_credited_once_per_paid_order_via_outbox(): void
    {
        $company = $this->company();
        $this->principal($company);

        RestaurantLoyaltyProgram::factory()->create([
            'company_id' => $company->id,
            'points_per_amount_minor' => 100,
            'is_active' => true,
        ]);

        /** @var RestaurantLoyaltyCustomer $customer */
        $customer = RestaurantLoyaltyCustomer::factory()->create([
            'company_id' => $company->id,
            'customer_contact_id' => 777,
            'points' => 0,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'customer_contact_id' => $customer->customer_contact_id,
            'status' => 'paid',
            'total_minor' => 2500,
            'currency' => 'XAF',
        ]);

        // Publication de l'événement comme le ferait PayOrderAction (RESTO-407).
        app(RestaurantOutboxPublisher::class)->publish(
            $company->id,
            'restaurant.order.paid.v1',
            ['order_id' => $order->id, 'total_minor' => 2500],
        );

        // Dispatch : 2500 / 100 = 25 points.
        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        $customer->refresh();
        $this->assertSame(25, $customer->points, '25 points pour 2500 minor units (100/point).');

        // Rejeu du dispatch → idempotent, aucun double crédit.
        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        $customer->refresh();
        $this->assertSame(25, $customer->points, 'Les points ne sont crédités qu\'une seule fois par commande payée.');
    }


    public function test_no_credit_without_opt_in(): void
    {
        $company = $this->company();
        $this->principal($company);

        RestaurantLoyaltyProgram::factory()->create([
            'company_id' => $company->id,
            'points_per_amount_minor' => 100,
            'is_active' => true,
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'customer_contact_id' => 999, // aucun compte fidélité (opt-in manquant)
            'status' => 'paid',
            'total_minor' => 5000,
            'currency' => 'XAF',
        ]);

        app(RestaurantOutboxPublisher::class)->publish(
            $company->id,
            'restaurant.order.paid.v1',
            ['order_id' => $order->id, 'total_minor' => 5000],
        );

        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        $this->assertSame(0, RestaurantLoyaltyCustomer::query()->count(), 'Pas de compte créé sans opt-in.');
    }
}
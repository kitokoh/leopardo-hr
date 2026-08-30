<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-606 (#6211) — Programme fidélité RestaurantManager.
 *
 * Couvre : CRUD du programme (un seul actif), opt-in client, crédit des
 * points à la commande payée via l'outbox (`restaurant.order.paid.v1`),
 * idempotence du crédit (« une seule fois par commande payée »), opt-in
 * requis (pas de compte → pas de crédit), échange jamais négatif et RBAC.
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

    public function test_redeem_never_goes_negative(): void
    {
        $company = $this->company();
        $this->manager($company);

        /** @var RestaurantLoyaltyCustomer $customer */
        $customer = RestaurantLoyaltyCustomer::factory()->create([
            'company_id' => $company->id,
            'points' => 10,
        ]);

        // Échange valide.
        $this->postJson("/api/v1/restaurant/loyalty-customers/{$customer->id}/redeem", ['points' => 4])
            ->assertStatus(200)
            ->assertJsonFragment(['points' => 6]);

        // Échange au-delà du solde → 422, jamais négatif.
        $this->postJson("/api/v1/restaurant/loyalty-customers/{$customer->id}/redeem", ['points' => 100])
            ->assertStatus(422);

        $customer->refresh();
        $this->assertSame(6, $customer->points);

        // Journal des mouvements : un gain éventuel + l'échange.
        $this->getJson("/api/v1/restaurant/loyalty-customers/{$customer->id}/movements")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}

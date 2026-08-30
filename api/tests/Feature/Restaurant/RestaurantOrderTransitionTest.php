<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-404 (#6191) — Transitions de commande (submit/confirm/serve/cancel).
 *
 * Couvre : transitions autorisées (submit draft→open, confirm open→
 * in_preparation, serve ready→served), transitions hors workflow refusées
 * (409), soumission sans article refusée (422), événement outbox
 * `restaurant.order.created.v1` publié à la soumission (idempotent, une
 * seule occurrence) et RBAC serveur+.
 */
class RestaurantOrderTransitionTest extends TestCase
{
    use RefreshTenantDatabase;

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
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
     * @return array{branch: RestaurantBranch, product: RestaurantProduct, order: RestaurantOrder}
     */
    private function makeDraftOrder(Company $company, string $status = 'draft'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($status): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 1900]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => $status,
                'currency' => $branch->currency,
            ]);

            return ['branch' => $branch, 'product' => $product, 'order' => $order];
        });
    }

    public function test_submit_moves_draft_to_open_and_publishes_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeDraftOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open');

        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.order.created.v1')
            ->count());

        $event = app(TenantManager::class)->withinTenant($company, fn () => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.order.created.v1')
            ->first());

        $this->assertNotNull($event);
        $this->assertSame($order->id, $event->payload_redacted['order_id']);
        $this->assertSame(1, $eventCount);
    }

    public function test_submit_without_items_is_refused_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makeDraftOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(422);
    }

    public function test_full_workflow_submit_confirm_serve(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeDraftOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/confirm")->assertStatus(200)->assertJsonPath('data.status', 'in_preparation');

        // in_preparation → served est hors workflow (ready requis) : 409.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/serve")->assertStatus(409);

        // Le passage à ready se fait côté cuisine (RESTO-410) — rôle kitchen.
        /** @var Employee $kitchen */
        $kitchen = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);
        Sanctum::actingAs($kitchen);

        $this->postJson("/api/v1/restaurant/kitchen/orders/{$order->id}/ready")->assertStatus(200)->assertJsonPath('data.status', 'ready');

        // Retour au rôle serveur pour le service en salle.
        $this->server($company);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/serve")->assertStatus(200)->assertJsonPath('data.status', 'served');
    }

    public function test_illegal_transition_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeDraftOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);

        // open → open n'existe pas (double submit) : 409.
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(409);
    }

    public function test_cancel_from_open_moves_to_cancelled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order, 'product' => $product] = $this->makeDraftOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/items", ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$order->id}/cancel")->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-412 (#6199) — Clôture de caisse → événement `restaurant.pos.closed.v1`.
 *
 * La clôture publie l'événement dans l'outbox APRÈS commit (payload redigé :
 * totaux recalculés serveur, écart, période, encaissements par provider).
 * Critère d'acceptation : clôture rejouable sans doublon — une session ne
 * produit qu'UN événement (clé d'idempotence `pos-closed-{id}`, rejeu → 409).
 */
class RestaurantPosClosedEventTest extends TestCase
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
     * @return array{branch: RestaurantBranch, session: RestaurantPosSession, order: RestaurantOrder}
     */
    private function makeSessionWithPaidOrder(Company $company, int $openingCash = 10000): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $openingCash): array {
            $branch = RestaurantBranch::factory()->create();
            $session = RestaurantPosSession::factory()->create([
                'branch_id' => $branch->id,
                'opening_cash_minor' => $openingCash,
                'status' => 'open',
                'version' => 1,
            ]);
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'pos_session_id' => $session->id,
                'status' => 'served',
                'currency' => $branch->currency,
            ]);
            RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1500,
                'line_total_minor' => 1500,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);
            $order->forceFill([
                'subtotal_minor' => 1500,
                'tax_minor' => 0,
                'total_minor' => 1500,
            ])->save();

            return ['branch' => $branch, 'session' => $session, 'order' => $order];
        });
    }

    public function test_close_publishes_pos_closed_event_with_server_computed_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['session' => $session, 'order' => $order] = $this->makeSessionWithPaidOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1500,
        ])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/pos-sessions/{$session->id}/close", [
            'counted_cash_minor' => 11500,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_minor', 11500)
            ->assertJsonPath('data.variance_minor', 0);

        $event = app(TenantManager::class)->withinTenant($company, fn () => RestaurantOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', 'restaurant.pos.closed.v1')
            ->first());

        $this->assertNotNull($event);
        $this->assertSame((int) $session->id, $event->payload_redacted['pos_session_id']);
        $this->assertSame(11500, $event->payload_redacted['expected_cash_minor']);
        $this->assertSame(0, $event->payload_redacted['variance_minor']);
        $this->assertSame(1500, $event->payload_redacted['payments_confirmed_minor']['cash']);
        $this->assertArrayHasKey('opened_at', $event->payload_redacted);
        $this->assertArrayHasKey('closed_at', $event->payload_redacted);
    }

    public function test_close_is_replayable_without_duplicate_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['session' => $session, 'order' => $order] = $this->makeSessionWithPaidOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1500,
        ])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/pos-sessions/{$session->id}/close", [
            'counted_cash_minor' => 11500,
        ])->assertStatus(200);

        // Rejeu de la clôture : session déjà fermée → 409, aucun doublon.
        $this->postJson("/api/v1/restaurant/pos-sessions/{$session->id}/close", [
            'counted_cash_minor' => 11500,
        ])->assertStatus(409);

        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', 'restaurant.pos.closed.v1')
            ->count());

        $this->assertSame(1, $eventCount);
    }
}

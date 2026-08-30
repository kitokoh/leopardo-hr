<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-901 (#6230) — Golden journey GJ-RESTO-01 : caisse → commande →
 * paiement → clôture.
 *
 * Parcours roi de la verticale (MAT-013), enregistré dans
 * `dev-hub/tools/golden-journeys.json`. Exercé de bout en bout sur les
 * endpoints réels : ouverture de caisse, commande salle, ajout d'article,
 * transitions (submit → confirm → serve), encaissement cash, clôture de
 * caisse — et vérification des événements outbox métier publiés.
 */
class RestaurantGoldenJourneyTest extends TestCase
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
     * GJ-RESTO-01 — parcours complet.
     */
    public function test_golden_journey_caisse_commande_paiement_cloture(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        // Référentiel minimal : branche, table, produit taxable à 0 %.
        [$branchId, $tableId, $productId] = app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id]);
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            return [$branch->id, $table->id, $product->id];
        });

        // 1) Ouverture de caisse (fonds 10000).
        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branchId,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        // 2) Création de la commande (salle, table, 2 couverts).
        $orderId = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branchId,
            'order_type' => 'dine_in',
            'table_id' => $tableId,
            'covers' => 2,
            'pos_session_id' => $sessionId,
            'idempotency_key' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        // 3) Ajout d'un article ×2 (prix serveur : 1500 → total 3000).
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/items", [
            'product_id' => $productId,
            'quantity' => 2,
        ])->assertStatus(201);

        // 4) Transitions : soumission → confirmation → cuisine ready → service.
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/submit")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_preparation');
        // La cuisine (rôle kitchen) marque la commande prête.
        /** @var Employee $kitchen */
        $kitchen = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);
        Sanctum::actingAs($kitchen);
        $this->postJson("/api/v1/restaurant/kitchen/orders/{$orderId}/ready")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        // Retour au service pour servir / encaisser / clôturer.
        $this->server($company);
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/serve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'served');

        // 5) Encaissement cash (montant vérifié serveur).
        $this->postJson("/api/v1/restaurant/orders/{$orderId}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 3000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        // 6) Clôture de caisse (rôle manage) : attendu = fonds 10000 + 3000 = 13000.
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 13000,
        ])->assertStatus(200);

        $closedEvent = app(TenantManager::class)->withinTenant($company, function () use ($sessionId) {
            return RestaurantOutboxEvent::query()
                ->where('event_type', 'restaurant.pos.closed.v1')
                ->first();
        });

        $this->assertNotNull($closedEvent);
        $this->assertSame($sessionId, $closedEvent->payload_redacted['pos_session_id']);
        $this->assertSame(13000, $closedEvent->payload_redacted['expected_cash_minor']);
        $this->assertSame(0, $closedEvent->payload_redacted['variance_minor']);

        // 7) Tous les événements du parcours roi sont publiés.
        $eventTypes = app(TenantManager::class)->withinTenant($company, fn (): array => RestaurantOutboxEvent::query()
            ->whereIn('event_type', [
                'restaurant.order.created.v1',
                'restaurant.payment.confirmed.v1',
                'restaurant.order.paid.v1',
                'restaurant.pos.closed.v1',
            ])
            ->pluck('event_type')
            ->all());

        $this->assertContains('restaurant.order.created.v1', $eventTypes);
        $this->assertContains('restaurant.payment.confirmed.v1', $eventTypes);
        $this->assertContains('restaurant.order.paid.v1', $eventTypes);
        $this->assertContains('restaurant.pos.closed.v1', $eventTypes);
    }
}

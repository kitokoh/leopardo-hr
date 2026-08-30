<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
<<<<<<< HEAD
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
=======
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
<<<<<<< HEAD
 * RESTO-901 (#6230) — Golden journey GJ-RESTO-01 (caisse → commande →
 * paiement → clôture).
 *
 * Parcours ROYAL de la verticale RestaurantManager, verrouillé de bout en
 * bout : ouverture de caisse → commande → article → soumission → cuisine
 * (start/ready) → service → addition → encaissement espèces → clôture de
 * caisse avec écart calculé serveur. Chaque étape valide un invariant de la
 * verticale (RESTO-401/402/403/404/410/407/412). Enregistré dans
 * `dev-hub/tools/golden-journeys.json` (GJ-RESTO-01) — garde
 * check-golden-journeys.sh.
=======
 * RESTO-901 (#6230) — Golden journey GJ-RESTO-01 : caisse → commande →
 * paiement → clôture.
 *
 * Parcours roi de la verticale (MAT-013), enregistré dans
 * `dev-hub/tools/golden-journeys.json`. Exercé de bout en bout sur les
 * endpoints réels : ouverture de caisse, commande salle, ajout d'article,
 * transitions (submit → confirm → serve), encaissement cash, clôture de
 * caisse — et vérification des événements outbox métier publiés.
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
 */
class RestaurantGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

<<<<<<< HEAD
    private function principal(Company $company): Employee
=======
    private function server(Company $company): Employee
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
<<<<<<< HEAD
            'manager_role' => 'principal',
=======
            'manager_role' => 'server',
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
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
<<<<<<< HEAD
     * @return array{branch: RestaurantBranch, product: RestaurantProduct}
     */
    private function referential(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'code' => 'DISH-ROYAL',
                'price_minor' => 3000,
                'currency' => 'XAF',
                'tax_rate_id' => null,
                'is_available' => true,
            ]);

            return ['branch' => $branch, 'product' => $product];
        });
    }

    public function test_golden_journey_gj_resto_01(): void
=======
     * GJ-RESTO-01 — parcours complet.
     */
    public function test_golden_journey_caisse_commande_paiement_cloture(): void
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
<<<<<<< HEAD
        $this->principal($company);
        ['branch' => $branch, 'product' => $product] = $this->referential($company);

        // 1. Ouverture de caisse (fonds d'ouverture 10 000).
        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
=======
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
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

<<<<<<< HEAD
        // 2. Création de la commande (à emporter).
        $orderId = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'pos_session_id' => $sessionId,
            'order_type' => 'takeaway',
            'idempotency_key' => 'gj-order-1',
=======
        // 2) Création de la commande (salle, table, 2 couverts).
        $orderId = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branchId,
            'order_type' => 'dine_in',
            'table_id' => $tableId,
            'covers' => 2,
            'pos_session_id' => $sessionId,
            'idempotency_key' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

<<<<<<< HEAD
        // 3. Ajout d'un article (prix du référentiel, jamais du client).
        $this->postJson('/api/v1/restaurant/orders/'.$orderId.'/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(201);

        // 4. Soumission → commande ouverte.
        $this->postJson('/api/v1/restaurant/orders/'.$orderId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        // 5. Cuisine : démarrage puis prête.
        $this->postJson('/api/v1/restaurant/kitchen/orders/'.$orderId.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_preparation');

        $this->postJson('/api/v1/restaurant/kitchen/orders/'.$orderId.'/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        // 6. Service.
        $this->postJson('/api/v1/restaurant/orders/'.$orderId.'/serve')
            ->assertOk()
            ->assertJsonPath('data.status', 'served');

        // 7. Addition : totaux serveur (2 × 3 000).
        $this->getJson('/api/v1/restaurant/orders/'.$orderId.'/bill')
            ->assertOk()
            ->assertJsonPath('data.total_minor', 6000);

        // 8. Encaissement espèces (montant vérifié serveur).
        $this->postJson('/api/v1/restaurant/orders/'.$orderId.'/pay', [
            'provider_code' => 'cash',
            'amount_minor' => 6000,
            'idempotency_key' => 'gj-pay-1',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        // 9. Clôture de caisse : attendu = 10 000 + 6 000, compté = 16 000
        //    → écart nul.
        $this->postJson('/api/v1/restaurant/pos-sessions/'.$sessionId.'/close', [
            'counted_cash_minor' => 16000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.variance_minor', 0);
=======
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
>>>>>>> abebd3dc1 (feat(restaurant): golden journey GJ-RESTO-01 — caisse → commande → paiement → clôture (RESTO-901, #6230))
    }
}

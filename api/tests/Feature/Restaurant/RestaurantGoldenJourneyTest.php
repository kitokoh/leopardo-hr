<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
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
 */
class RestaurantGoldenJourneyTest extends TestCase
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

    /**
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
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);
        ['branch' => $branch, 'product' => $product] = $this->referential($company);

        // 1. Ouverture de caisse (fonds d'ouverture 10 000).
        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        // 2. Création de la commande (à emporter).
        $orderId = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'pos_session_id' => $sessionId,
            'order_type' => 'takeaway',
            'idempotency_key' => 'gj-order-1',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

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
    }
}

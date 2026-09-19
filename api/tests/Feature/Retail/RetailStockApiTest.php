<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7673) — API privée de gestion de stock du module vendeur :
 * CRUD emplacements, mouvements tracés (transaction + verrou, stock jamais
 * négatif), alertes de stock bas, RBAC deny-by-default, isolation tenant
 * et gate feature flag retail.
 */
class RetailStockApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyA->setFeature('retail', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('retail', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = [
            'company_id' => $company->id,
            'status' => 'active',
        ];

        if ($managerRole === 'employee') {
            $attributes['role'] = 'employee';
        } else {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create($attributes);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeLocation(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/locations', array_merge([
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\JsonResponse>
     */
    private function postMovement(Employee $actor, int $locationId, int $productId, array $overrides = [])
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/stock/movements', array_merge([
            'location_id' => $locationId,
            'product_id' => $productId,
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
        ], $overrides));
    }

    public function test_feature_flag_gate_returns_403_when_disabled(): void
    {
        /** @var Employee $manager */
        $manager = $this->employee($this->companyNoFlag, 'principal');
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/retail/locations', ['name' => 'Boutique', 'code' => 'S1'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->getJson('/api/v1/retail/stock/levels')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->getJson('/api/v1/retail/stock/alerts')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_location_crud_by_manager(): void
    {
        $location = $this->storeLocation($this->principalA, ['type' => 'warehouse']);
        $this->assertSame('warehouse', $location['type']);
        $this->assertSame('STORE-01', $location['code']);
        $this->assertSame($this->companyA->id, $location['company_id']);
        $this->assertTrue($location['is_active']);

        // Code déjà pris dans le tenant → 422 (unicité scoped company_id).
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/locations', ['name' => 'Doublon', 'code' => 'STORE-01'])
            ->assertStatus(422);

        // Même code dans un AUTRE tenant → 201.
        $this->storeLocation($this->principalB);

        // Type hors enum → 422.
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/locations', ['name' => 'Kiosque', 'code' => 'K1', 'type' => 'truck'])
            ->assertStatus(422);

        // Mise à jour (nom + désactivation).
        $this->putJson("/api/v1/retail/locations/{$location['id']}", [
            'name' => 'Entrepot nord',
            'is_active' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Entrepot nord')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.code', 'STORE-01');

        // Liste + filtre.
        $this->getJson('/api/v1/retail/locations?type=warehouse')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Suppression.
        $this->deleteJson("/api/v1/retail/locations/{$location['id']}")
            ->assertStatus(200);
        $this->getJson("/api/v1/retail/locations/{$location['id']}")
            ->assertStatus(404);
    }

    public function test_employee_cannot_write_but_can_read_stock(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storeProduct($this->principalA);

        $this->actingAsUser($this->employeeA);

        // Écritures : 403 (RBAC deny-by-default).
        $this->postJson('/api/v1/retail/locations', ['name' => 'Pirate', 'code' => 'P1'])
            ->assertStatus(403);
        $this->putJson("/api/v1/retail/locations/{$location['id']}", ['name' => 'Pirate'])
            ->assertStatus(403);
        $this->deleteJson("/api/v1/retail/locations/{$location['id']}")
            ->assertStatus(403);
        $this->postMovement($this->employeeA, (int) $location['id'], (int) $product['id'])
            ->assertStatus(403);

        // Lectures : autorisées aux membres du tenant.
        $this->actingAsUser($this->employeeA);
        $this->getJson('/api/v1/retail/locations')->assertStatus(200);
        $this->getJson("/api/v1/retail/locations/{$location['id']}")->assertStatus(200);
        $this->getJson('/api/v1/retail/stock/levels')->assertStatus(200);
        $this->getJson('/api/v1/retail/stock/movements')->assertStatus(200);
        $this->getJson('/api/v1/retail/stock/alerts')->assertStatus(200);
    }

    public function test_movements_create_level_and_are_traced(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storeProduct($this->principalA);

        // Réception d'achat +10 : le niveau est créé à la volée.
        $purchase = $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
            'reference_type' => 'purchase_order',
            'reference_id' => 42,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.movement.reason_code', 'purchase')
            ->assertJsonPath('data.movement.reference_type', 'purchase_order')
            ->assertJsonPath('data.level.quantity', '10.000')
            ->json('data');

        // Vente -3 : quantité 7, mouvement lié au même niveau.
        $sale = $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => -3,
            'reason_code' => 'sale',
            'note' => 'Ticket caisse 0001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.movement.reason_code', 'sale')
            ->assertJsonPath('data.movement.quantity_delta', '-3.000')
            ->assertJsonPath('data.level.quantity', '7.000')
            ->json('data');

        $this->assertSame($purchase['level']['id'], $sale['level']['id']);
        $this->assertSame($sale['movement']['stock_level_id'], $sale['level']['id']);
        $this->assertSame((int) $this->principalA->id, $sale['movement']['user_id']);

        // Journal : 2 mouvements tracés, ordre anté-chronologique.
        $this->getJson('/api/v1/retail/stock/movements?product_id='.$product['id'])
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.reason_code', 'sale')
            ->assertJsonPath('data.1.reason_code', 'purchase');

        // Filtre par reason_code.
        $this->getJson('/api/v1/retail/stock/movements?reason_code=purchase')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(2, RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->count());
        $this->assertSame(1, RetailStockLevel::query()
            ->where('company_id', $this->companyA->id)
            ->count());
    }

    public function test_negative_resulting_stock_is_rejected_and_nothing_persisted(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => 5,
        ])->assertStatus(201);

        // -8 sur un stock de 5 → 422 + rien n'est persisté (transaction).
        $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => -8,
            'reason_code' => 'sale',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_delta']);

        /** @var RetailStockLevel $level */
        $level = RetailStockLevel::query()
            ->where('company_id', $this->companyA->id)
            ->firstOrFail();
        $this->assertSame('5.000', $level->quantity);

        $this->assertSame(1, RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->count());

        // Delta nul → 422 (validation).
        $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => 0,
        ])->assertStatus(422);

        // Trop de décimales → 422 (validation).
        $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'quantity_delta' => 1.2345,
        ])->assertStatus(422);

        // reason_code hors enum → 422 (validation).
        $this->postMovement($this->principalA, (int) $location['id'], (int) $product['id'], [
            'reason_code' => 'theft',
        ])->assertStatus(422);
    }

    public function test_below_threshold_filter_and_alerts(): void
    {
        $location = $this->storeLocation($this->principalA);
        $productLow = $this->storeProduct($this->principalA, ['sku' => 'SKU-LOW']);
        $productOk = $this->storeProduct($this->principalA, ['sku' => 'SKU-OK']);

        $this->postMovement($this->principalA, (int) $location['id'], (int) $productLow['id'], [
            'quantity_delta' => 2,
        ])->assertStatus(201);
        $this->postMovement($this->principalA, (int) $location['id'], (int) $productOk['id'], [
            'quantity_delta' => 50,
        ])->assertStatus(201);

        // Seuils d'alerte (métadonnées, pas des quantités : écriture directe OK).
        RetailStockLevel::query()
            ->where('company_id', $this->companyA->id)
            ->where('product_id', $productLow['id'])
            ->update(['alert_threshold' => '5.000']);
        RetailStockLevel::query()
            ->where('company_id', $this->companyA->id)
            ->where('product_id', $productOk['id'])
            ->update(['alert_threshold' => '10.000']);

        // Filtre below_threshold : seul le produit bas apparaît (2 <= 5).
        $this->actingAsUser($this->principalA);
        $this->getJson('/api/v1/retail/stock/levels?below_threshold=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_id', $productLow['id']);

        // Sans le filtre : les deux niveaux.
        $this->getJson('/api/v1/retail/stock/levels')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // /stock/alerts : même périmètre que le filtre.
        $this->getJson('/api/v1/retail/stock/alerts')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_id', $productLow['id']);

        // Égalité au seuil : quantité 5 <= 5 → alerte aussi.
        $this->postMovement($this->principalA, (int) $location['id'], (int) $productLow['id'], [
            'quantity_delta' => 3,
        ])->assertStatus(201);
        $this->getJson('/api/v1/retail/stock/alerts')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cross_tenant_isolation_no_leakage(): void
    {
        $locationA = $this->storeLocation($this->principalA);
        $productA = $this->storeProduct($this->principalA);
        $locationB = $this->storeLocation($this->principalB, ['code' => 'STORE-B']);

        // Mouvement du tenant B vers un emplacement/produit du tenant A →
        // 422 (Rule::exists scoped company_id, pas de fuite).
        $this->postMovement($this->principalB, (int) $locationA['id'], (int) $productA['id'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id', 'product_id']);

        // Rien n'a été créé dans aucun tenant.
        $this->assertSame(0, RetailInventoryMovement::query()->count());
        $this->assertSame(0, RetailStockLevel::query()->count());

        // Emplacement du tenant A introuvable pour B (404, fail-closed).
        $this->actingAsUser($this->principalB);
        $this->getJson("/api/v1/retail/locations/{$locationA['id']}")
            ->assertStatus(404);
        $this->putJson("/api/v1/retail/locations/{$locationA['id']}", ['name' => 'Pirate'])
            ->assertStatus(404);
        $this->deleteJson("/api/v1/retail/locations/{$locationA['id']}")
            ->assertStatus(404);

        // Les listes du tenant B ne voient pas les données de A.
        $stockA = $this->postMovement($this->principalA, (int) $locationA['id'], (int) $productA['id'])
            ->assertStatus(201);

        $this->actingAsUser($this->principalB);
        $this->getJson('/api/v1/retail/stock/levels')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/retail/stock/movements')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/retail/locations')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $locationB['id']);
    }
}

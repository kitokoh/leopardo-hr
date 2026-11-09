<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Domain\Models\RetailStockTransfer;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL — audit de la gestion de stock multi-tenant (#9268, #9269,
 * #9270).
 *
 * Trois garanties verrouillees ici, chacune avec sa preuve de non-regression :
 *
 *  - **#9268 transferts** : deux mouvements atomiques lies par le meme
 *    `reference_id` ; un stock source insuffisant n'en laisse AUCUN (pas de
 *    sortie sans entree) ; source = destination refusee.
 *  - **#9269 cout moyen pondere** : une entree a cout different deplace la
 *    moyenne, une sortie ne la deplace pas, et la valorisation utilise la
 *    moyenne avec `cost_source = average` (repli `catalog` sinon).
 *  - **#9270 idempotence** : un rejeu avec la meme cle ne reapplique pas le
 *    delta ; la cle est scopee tenant.
 */
class RetailStockAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

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

        $this->principalA = $this->employee($this->companyA);
        $this->principalB = $this->employee($this->companyB);
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeLocation(Employee $actor, array $overrides = []): array
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/v1/retail/locations', array_merge([
            'name' => 'Boutique',
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
        Sanctum::actingAs($actor);

        return $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'cost_minor' => 1_000,
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
        Sanctum::actingAs($actor);

        return $this->postJson('/api/v1/retail/stock/movements', array_merge([
            'location_id' => $locationId,
            'product_id' => $productId,
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
        ], $overrides));
    }

    private function level(int $locationId, int $productId): RetailStockLevel
    {
        /** @var RetailStockLevel $level */
        $level = RetailStockLevel::query()
            ->where('company_id', $this->companyA->id)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->firstOrFail();

        return $level;
    }

    // ── #9268 — transferts ────────────────────────────────────────────────

    public function test_transfer_creates_two_linked_movements(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $warehouse = $this->storeLocation($this->principalA, ['code' => 'WH-01', 'type' => 'warehouse']);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $warehouse['id'], (int) $product['id'], [
            'quantity_delta' => 50,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalA);
        $response = $this->postJson('/api/v1/retail/stock/transfers', [
            'from_location_id' => (int) $warehouse['id'],
            'to_location_id' => (int) $store['id'],
            'product_id' => (int) $product['id'],
            'quantity' => 20,
            'note' => 'Reassort boutique',
        ])->assertStatus(201);

        $transferId = (int) $response->json('data.id');

        // Les deux mouvements existent et partagent la reference du transfert.
        $movements = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reference_type', 'retail_transfer')
            ->where('reference_id', $transferId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame('transfer_out', $movements[0]->reason_code->value);
        $this->assertSame('transfer_in', $movements[1]->reason_code->value);
        $this->assertSame(-20.0, (float) $movements[0]->quantity_delta);
        $this->assertSame(20.0, (float) $movements[1]->quantity_delta);

        // Les niveaux suivent : 30 restants a l'entrepot, 20 en boutique.
        $this->assertSame(30.0, (float) $this->level((int) $warehouse['id'], (int) $product['id'])->quantity);
        $this->assertSame(20.0, (float) $this->level((int) $store['id'], (int) $product['id'])->quantity);
    }

    public function test_transfer_with_insufficient_source_stock_persists_nothing(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $warehouse = $this->storeLocation($this->principalA, ['code' => 'WH-01', 'type' => 'warehouse']);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $warehouse['id'], (int) $product['id'], [
            'quantity_delta' => 5,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalA);
        $this->postJson('/api/v1/retail/stock/transfers', [
            'from_location_id' => (int) $warehouse['id'],
            'to_location_id' => (int) $store['id'],
            'product_id' => (int) $product['id'],
            'quantity' => 99,
        ])->assertStatus(422);

        // Aucune ligne de transfert, aucun mouvement de transfert : la
        // transaction a tout annule (pas de sortie sans entree).
        $this->assertSame(0, RetailStockTransfer::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(
            0,
            RetailInventoryMovement::query()
                ->where('company_id', $this->companyA->id)
                ->whereIn('reason_code', ['transfer_in', 'transfer_out'])
                ->count()
        );

        // Le stock source est intact.
        $this->assertSame(5.0, (float) $this->level((int) $warehouse['id'], (int) $product['id'])->quantity);
    }

    public function test_transfer_to_same_location_is_rejected(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalA);
        $this->postJson('/api/v1/retail/stock/transfers', [
            'from_location_id' => (int) $store['id'],
            'to_location_id' => (int) $store['id'],
            'product_id' => (int) $product['id'],
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_transfer_cannot_cross_tenants(): void
    {
        $storeA = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $productA = $this->storeProduct($this->principalA);

        $storeB = $this->storeLocation($this->principalB, ['code' => 'STORE-01']);

        $this->postMovement($this->principalA, (int) $storeA['id'], (int) $productA['id'], [
            'quantity_delta' => 10,
        ])->assertStatus(201);

        // L'emplacement de destination appartient a un AUTRE tenant : la
        // regle Rule::exists scoped company_id le refuse (422), pas 404.
        Sanctum::actingAs($this->principalA);
        $this->postJson('/api/v1/retail/stock/transfers', [
            'from_location_id' => (int) $storeA['id'],
            'to_location_id' => (int) $storeB['id'],
            'product_id' => (int) $productA['id'],
            'quantity' => 1,
        ])->assertStatus(422);
    }

    // ── #9269 — cout moyen pondere ────────────────────────────────────────

    public function test_weighted_average_cost_moves_on_entry_and_not_on_exit(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA);

        // 10 unites a 1 000 -> moyenne 1 000.
        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'unit_cost_minor' => 1_000,
        ])->assertStatus(201);

        $this->assertSame(1_000, $this->level((int) $store['id'], (int) $product['id'])->avg_cost_minor);

        // 10 unites a 2 000 -> (10*1000 + 10*2000) / 20 = 1 500.
        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'unit_cost_minor' => 2_000,
        ])->assertStatus(201);

        $this->assertSame(1_500, $this->level((int) $store['id'], (int) $product['id'])->avg_cost_minor);

        // Sortie de 5 : la moyenne NE BOUGE PAS (cout moyen pondere, pas FIFO).
        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => -5,
            'reason_code' => 'sale',
        ])->assertStatus(201);

        $this->assertSame(1_500, $this->level((int) $store['id'], (int) $product['id'])->avg_cost_minor);
    }

    public function test_entry_without_unit_cost_does_not_invent_a_cost(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
        ])->assertStatus(201);

        // Aucun cout fourni : le cout moyen reste inconnu (null), il n'est
        // pas invente a partir du cout catalogue.
        $this->assertNull($this->level((int) $store['id'], (int) $product['id'])->avg_cost_minor);
    }

    public function test_stock_valuation_uses_average_cost_and_reports_its_source(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA, ['cost_minor' => 1_000]);

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'unit_cost_minor' => 2_000,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalA);
        $report = $this->getJson('/api/v1/retail/reports/stock-valuation')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(20_000, $report['cost_value_minor']);
        $this->assertSame('average', $report['lines'][0]['cost_source']);
        $this->assertSame(2_000, $report['lines'][0]['unit_cost_minor']);
    }

    public function test_stock_valuation_falls_back_to_catalog_cost_explicitly(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA, ['cost_minor' => 1_000]);

        // Entree SANS cout unitaire : le niveau n'a pas de cout moyen.
        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalA);
        $report = $this->getJson('/api/v1/retail/reports/stock-valuation')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(10_000, $report['cost_value_minor']);
        $this->assertSame('catalog', $report['lines'][0]['cost_source']);
    }

    // ── #9270 — idempotence ───────────────────────────────────────────────

    public function test_replayed_movement_with_same_key_is_applied_once(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA);

        $payload = [
            'quantity_delta' => 10,
            'idempotency_key' => 'offline-receipt-0001',
        ];

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], $payload)
            ->assertStatus(201);

        // Rejeu apres reconnexion : meme cle, meme payload.
        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], $payload)
            ->assertStatus(201);

        $this->assertSame(
            1,
            RetailInventoryMovement::query()
                ->where('company_id', $this->companyA->id)
                ->where('idempotency_key', 'offline-receipt-0001')
                ->count()
        );

        // La quantite n'a ete appliquee qu'UNE fois.
        $this->assertSame(10.0, (float) $this->level((int) $store['id'], (int) $product['id'])->quantity);
    }

    public function test_distinct_keys_create_distinct_movements(): void
    {
        $store = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $product = $this->storeProduct($this->principalA);

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'idempotency_key' => 'offline-receipt-0001',
        ])->assertStatus(201);

        $this->postMovement($this->principalA, (int) $store['id'], (int) $product['id'], [
            'quantity_delta' => 10,
            'idempotency_key' => 'offline-receipt-0002',
        ])->assertStatus(201);

        $this->assertSame(20.0, (float) $this->level((int) $store['id'], (int) $product['id'])->quantity);
    }

    public function test_same_key_in_two_tenants_does_not_collide(): void
    {
        $storeA = $this->storeLocation($this->principalA, ['code' => 'STORE-01']);
        $productA = $this->storeProduct($this->principalA);

        $storeB = $this->storeLocation($this->principalB, ['code' => 'STORE-01']);
        $productB = $this->storeProduct($this->principalB);

        $this->postMovement($this->principalA, (int) $storeA['id'], (int) $productA['id'], [
            'quantity_delta' => 10,
            'idempotency_key' => 'shared-key-0001',
        ])->assertStatus(201);

        $this->postMovement($this->principalB, (int) $storeB['id'], (int) $productB['id'], [
            'quantity_delta' => 7,
            'idempotency_key' => 'shared-key-0001',
        ])->assertStatus(201);

        $this->assertSame(10.0, (float) $this->level((int) $storeA['id'], (int) $productA['id'])->quantity);
        $this->assertSame(
            7.0,
            (float) RetailStockLevel::query()
                ->where('company_id', $this->companyB->id)
                ->where('location_id', (int) $storeB['id'])
                ->where('product_id', (int) $productB['id'])
                ->firstOrFail()
                ->quantity
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyStockService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stock d'officine — PHARMA-003 (#7800).
 *
 * Couvre : solution inactive 403, FEFO multi-lots (péremptions croissantes),
 * exclusion des lots périmés du disponible, stock insuffisant 422
 * PHARMACY_INSUFFICIENT_STOCK sans effet partiel, ajustement (raison
 * obligatoire, jamais de lot négatif, manager seulement), journal immuable,
 * alertes (sous seuil / périmant / périmés), isolation tenant.
 */
class PharmacyStockTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $managerA;

    private Employee $lambdaA;

    private Employee $managerB;

    private PharmacyProduct $productA;

    private function baseUrl(): string
    {
        return '/api/v1/pharmacy';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['pharmacy' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['pharmacy' => true],
        ]);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerA = $managerA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerB = $managerB;

        /** @var PharmacyProduct $productA */
        $productA = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Paracétamol 500mg',
            'min_stock_level' => 10,
        ]);
        $this->productA = $productA;
    }

    private function service(): PharmacyStockService
    {
        return app(PharmacyStockService::class);
    }

    private function receiveBatch(string $batchNumber, string $expiry, int $quantity, string $cost = '100.00'): PharmacyBatch
    {
        return $this->service()->receive(
            (string) $this->companyA->id,
            (int) $this->productA->id,
            $batchNumber,
            $expiry,
            $quantity,
            $cost,
        );
    }

    public function test_inactive_solution_gets_403_on_stock_routes(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl().'/stock')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/alerts')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => 1,
            'quantity_delta' => -1,
            'reason' => 'test solution inactive',
        ])->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }

    public function test_receive_creates_batch_and_receipt_movement(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 50);

        $this->assertSame(50, $batch->quantity);
        $this->assertSame(1, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('batch_id', $batch->id)
            ->where('type', 'receipt')
            ->where('quantity_delta', 50)
            ->count());

        // Ré-réception sur le même lot : incrément, pas de doublon de lot.
        $again = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 25);
        $this->assertSame($batch->id, $again->id);
        $this->assertSame(75, $again->quantity);
    }

    public function test_dispense_fefo_consumes_earliest_expiry_first_and_skips_expired(): void
    {
        $expired = $this->receiveBatch('LOT-EXPIRED', Carbon::today()->subDay()->toDateString(), 100);
        $near = $this->receiveBatch('LOT-NEAR', Carbon::today()->addDays(30)->toDateString(), 5);
        $far = $this->receiveBatch('LOT-FAR', Carbon::today()->addDays(300)->toDateString(), 20);

        $dispensed = $this->service()->dispenseFefo((string) $this->companyA->id, (int) $this->productA->id, 8);

        // 5 du lot proche, 3 du lot lointain — jamais le lot périmé.
        $this->assertSame([
            ['batch_id' => (int) $near->id, 'quantity' => 5],
            ['batch_id' => (int) $far->id, 'quantity' => 3],
        ], array_map(fn (array $line): array => ['batch_id' => $line['batch_id'], 'quantity' => $line['quantity']], $dispensed));

        $this->assertSame(0, $near->refresh()->quantity);
        $this->assertSame(17, $far->refresh()->quantity);
        $this->assertSame(100, $expired->refresh()->quantity);

        // Chaque décrément trace un mouvement `sale` négatif.
        $this->assertSame(2, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'sale')
            ->count());
    }

    public function test_dispense_rejects_insufficient_stock_without_partial_effect(): void
    {
        $this->receiveBatch('LOT-EXPIRED', Carbon::today()->subDay()->toDateString(), 100);
        $near = $this->receiveBatch('LOT-NEAR', Carbon::today()->addDays(30)->toDateString(), 5);

        try {
            $this->service()->dispenseFefo((string) $this->companyA->id, (int) $this->productA->id, 6);
            $this->fail('PHARMACY_INSUFFICIENT_STOCK attendu');
        } catch (\App\Modules\Pharmacy\Domain\Exceptions\PharmacyInsufficientStockException $exception) {
            $this->assertSame('PHARMACY_INSUFFICIENT_STOCK', $exception->errorCode());
            $this->assertSame(422, $exception->statusCode());
        }

        // Aucun effet partiel : le lot proche est intact, aucun mouvement `sale`.
        $this->assertSame(5, $near->refresh()->quantity);
        $this->assertSame(0, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'sale')
            ->count());
    }

    public function test_adjustment_endpoint_requires_manager_and_reason_and_never_negative(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 10);

        // Employé lambda → 403 (RBAC manager).
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -1,
            'reason' => 'casse',
        ])->assertStatus(403);

        Sanctum::actingAs($this->managerA);

        // Raison obligatoire.
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        // Jamais de lot négatif.
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -11,
            'reason' => 'inventaire annuel',
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INSUFFICIENT_STOCK');

        // Ajustement valide → mouvement `adjustment` tracé.
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -3,
            'reason' => 'casse comptoir',
        ])->assertStatus(201)->assertJsonPath('data.quantity', 7);

        $this->assertSame(1, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'adjustment')
            ->where('quantity_delta', -3)
            ->where('reason', 'casse comptoir')
            ->count());

        // Lot d'un autre tenant → 404 (aucune fuite).
        Sanctum::actingAs($this->managerB);
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -1,
            'reason' => 'vol cross-tenant',
        ])->assertStatus(404);
        $this->assertSame(7, $batch->refresh()->quantity);
    }

    public function test_stock_levels_exclude_expired_batches(): void
    {
        $this->receiveBatch('LOT-EXPIRED', Carbon::today()->subDay()->toDateString(), 40);
        $this->receiveBatch('LOT-OK', Carbon::today()->addDays(60)->toDateString(), 6);

        Sanctum::actingAs($this->managerA);
        $this->getJson($this->baseUrl().'/stock')
            ->assertStatus(200)
            ->assertJsonPath('data.0.available_quantity', 6)
            ->assertJsonPath('data.0.expired_quantity', 40)
            ->assertJsonPath('data.0.below_min_stock', true);

        $this->getJson($this->baseUrl().'/stock/products/'.$this->productA->id.'/batches')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.expired', true);
    }

    public function test_alerts_report_low_stock_expiring_and_expired(): void
    {
        $this->receiveBatch('LOT-EXPIRED', Carbon::today()->subDays(2)->toDateString(), 15);
        $this->receiveBatch('LOT-SOON', Carbon::today()->addDays(20)->toDateString(), 4);
        $this->receiveBatch('LOT-FAR', Carbon::today()->addDays(400)->toDateString(), 2);

        Sanctum::actingAs($this->managerA);
        $response = $this->getJson($this->baseUrl().'/alerts')->assertStatus(200);

        // 4 + 2 = 6 disponibles < seuil 10 → sous seuil.
        $response->assertJsonPath('data.low_stock.0.product_id', (int) $this->productA->id)
            ->assertJsonPath('data.low_stock.0.available_quantity', 6)
            ->assertJsonCount(1, 'data.expiring_soon')
            ->assertJsonPath('data.expiring_soon.0.batch_number', 'LOT-SOON')
            ->assertJsonCount(1, 'data.expired')
            ->assertJsonPath('data.expired.0.batch_number', 'LOT-EXPIRED');

        // Fenêtre personnalisée : rien ne périme sous 5 jours.
        $this->getJson($this->baseUrl().'/alerts?days=5')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.expiring_soon');

        // Tenant B ne voit aucune alerte du tenant A.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/alerts')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.low_stock')
            ->assertJsonCount(0, 'data.expired');
    }

    public function test_movements_journal_is_immutable_and_tenant_scoped(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 10);

        /** @var PharmacyStockMovement $movement */
        $movement = PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->firstOrFail();

        // Append-only : toute mise à jour/suppression est refusée.
        try {
            $movement->update(['quantity_delta' => 999]);
            $this->fail('LogicException attendue (journal immuable)');
        } catch (LogicException) {
        }

        try {
            $movement->delete();
            $this->fail('LogicException attendue (journal immuable)');
        } catch (LogicException) {
        }

        $this->assertSame(10, $movement->refresh()->quantity_delta);

        Sanctum::actingAs($this->managerA);
        $this->getJson($this->baseUrl().'/stock/movements?product_id='.$this->productA->id)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'receipt');

        // Tenant B : journal vide + lots du tenant A inaccessibles.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/stock/movements')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
        $this->getJson($this->baseUrl().'/stock/products/'.$this->productA->id.'/batches')
            ->assertStatus(404);

        $this->assertNotNull($batch->refresh());
    }
}

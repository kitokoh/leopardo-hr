<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Application\Services\PharmacyStockService;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyInsufficientStockException;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stock d'officine par lots — PHARMA-003 (#7800).
 *
 * Couvre : réception créant lot + mouvement `receipt`, incrément d'un lot
 * existant, disponible EXCLUANT les périmés, délivrance FEFO multi-lots
 * (ordre des péremptions, jamais de négatif, refus en bloc si insuffisant),
 * immuabilité du journal (UPDATE/DELETE interdits), ajustement d'inventaire
 * (raison obligatoire, manager uniquement, jamais de lot négatif), alertes
 * (rupture, péremption proche, périmés) et isolation tenant.
 */
class PharmacyStockTest extends TestCase
{
    use RefreshTenantDatabase;

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
        $productA = PharmacyProduct::query()->forceCreate([
            'company_id' => (string) $companyA->id,
            'name' => 'Doliprane 1000',
            'category' => 'medicament',
            'unit' => 'unite',
            'sale_price' => '120.00',
            'min_stock_level' => 10,
            'status' => 'active',
        ]);
        $this->productA = $productA;
    }

    private function stockService(): PharmacyStockService
    {
        return app(PharmacyStockService::class);
    }

    private function receiveBatch(string $number, string $expiry, int $quantity): PharmacyBatch
    {
        return $this->stockService()->receive(
            product: $this->productA,
            batchNumber: $number,
            expiryDate: Carbon::parse($expiry),
            quantity: $quantity,
        );
    }

    public function test_receive_creates_batch_and_receipt_movement_and_increments_existing_batch(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 20);

        $this->assertSame(20, $batch->quantity);
        $this->assertTrue(
            PharmacyStockMovement::query()
                ->where('batch_id', $batch->id)
                ->where('type', 'receipt')
                ->where('quantity_delta', 20)
                ->exists()
        );

        $again = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 5);

        $this->assertSame((int) $batch->id, (int) $again->id);
        $this->assertSame(25, $again->quantity);
        $this->assertSame(2, PharmacyStockMovement::query()->where('batch_id', $batch->id)->count());
    }

    public function test_available_stock_excludes_expired_batches(): void
    {
        $this->receiveBatch('LOT-OK', Carbon::today()->addMonths(6)->toDateString(), 10);
        $expired = $this->receiveBatch('LOT-OLD', Carbon::today()->addDay()->toDateString(), 30);
        PharmacyBatch::query()->whereKey($expired->id)->update(['expiry_date' => Carbon::today()->subDay()->toDateString()]);

        $this->assertSame(10, $this->stockService()->availableQuantity($this->productA));

        Sanctum::actingAs($this->managerA);
        $response = $this->getJson($this->baseUrl().'/stock/levels');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.available_quantity', 10)
            ->assertJsonPath('data.0.expired_quantity', 30);
    }

    public function test_dispense_fefo_consumes_batches_in_expiry_order_and_skips_expired(): void
    {
        $late = $this->receiveBatch('LOT-LATE', Carbon::today()->addYear()->toDateString(), 50);
        $soon = $this->receiveBatch('LOT-SOON', Carbon::today()->addMonth()->toDateString(), 5);
        $expired = $this->receiveBatch('LOT-DEAD', Carbon::today()->addDay()->toDateString(), 40);
        PharmacyBatch::query()->whereKey($expired->id)->update(['expiry_date' => Carbon::today()->subDay()->toDateString()]);

        $allocations = $this->stockService()->dispenseFefo($this->productA, 8);

        $this->assertSame([
            ['batch_id' => (int) $soon->id, 'quantity' => 5],
            ['batch_id' => (int) $late->id, 'quantity' => 3],
        ], $allocations);

        $this->assertSame(0, $soon->refresh()->quantity);
        $this->assertSame(47, $late->refresh()->quantity);
        $this->assertSame(40, $expired->refresh()->quantity);

        $this->assertTrue(
            PharmacyStockMovement::query()
                ->where('batch_id', $soon->id)
                ->where('type', 'sale')
                ->where('quantity_delta', -5)
                ->exists()
        );
    }

    public function test_dispense_fefo_refuses_when_non_expired_stock_is_insufficient(): void
    {
        $this->receiveBatch('LOT-A', Carbon::today()->addMonth()->toDateString(), 3);
        $expired = $this->receiveBatch('LOT-DEAD', Carbon::today()->addDay()->toDateString(), 100);
        PharmacyBatch::query()->whereKey($expired->id)->update(['expiry_date' => Carbon::today()->subDay()->toDateString()]);

        $this->expectException(PharmacyInsufficientStockException::class);

        try {
            $this->stockService()->dispenseFefo($this->productA, 4);
        } finally {
            // Aucun effet partiel : le lot valide reste intact.
            $this->assertSame(3, (int) PharmacyBatch::query()->where('batch_number', 'LOT-A')->firstOrFail()->quantity);
        }
    }

    public function test_stock_movements_are_append_only(): void
    {
        $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 10);

        /** @var PharmacyStockMovement $movement */
        $movement = PharmacyStockMovement::query()->firstOrFail();

        try {
            $movement->update(['quantity_delta' => 999]);
            $this->fail('Updating a stock movement should throw.');
        } catch (LogicException) {
            // attendu
        }

        try {
            $movement->delete();
            $this->fail('Deleting a stock movement should throw.');
        } catch (LogicException) {
            // attendu
        }

        $this->assertSame(10, PharmacyStockMovement::query()->firstOrFail()->quantity_delta);
    }

    public function test_adjustment_requires_manager_and_reason_and_never_goes_negative(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 10);

        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -2,
            'reason' => 'Casse',
        ])->assertStatus(403);

        Sanctum::actingAs($this->managerA);
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -2,
        ])->assertStatus(422);

        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -50,
            'reason' => 'Inventaire',
        ])->assertStatus(422);

        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -2,
            'reason' => 'Casse constatee en rayon',
        ])->assertStatus(201)
            ->assertJsonPath('data.quantity', 8);

        $this->assertTrue(
            PharmacyStockMovement::query()
                ->where('batch_id', $batch->id)
                ->where('type', 'adjustment')
                ->where('quantity_delta', -2)
                ->where('reason', 'Casse constatee en rayon')
                ->exists()
        );
    }

    public function test_alerts_report_low_stock_expiring_and_expired_batches(): void
    {
        // Sous le seuil (min 10, disponible 4) + un lot périmant sous 90 jours + un périmé.
        $this->receiveBatch('LOT-SOON', Carbon::today()->addDays(30)->toDateString(), 4);
        $expired = $this->receiveBatch('LOT-DEAD', Carbon::today()->addDay()->toDateString(), 7);
        PharmacyBatch::query()->whereKey($expired->id)->update(['expiry_date' => Carbon::today()->subDays(3)->toDateString()]);

        Sanctum::actingAs($this->lambdaA);
        $response = $this->getJson($this->baseUrl().'/alerts');

        $response->assertStatus(200)
            ->assertJsonPath('data.low_stock.0.product_id', (int) $this->productA->getAttribute('id'))
            ->assertJsonPath('data.low_stock.0.available_quantity', 4)
            ->assertJsonPath('data.expiring_soon.0.batch_number', 'LOT-SOON')
            ->assertJsonPath('data.expired.0.batch_number', 'LOT-DEAD')
            ->assertJsonPath('meta.expiry_window_days', 90);
    }

    public function test_stock_endpoints_are_tenant_isolated_and_fail_closed(): void
    {
        $batch = $this->receiveBatch('LOT-A', Carbon::today()->addYear()->toDateString(), 10);

        // Le manager du tenant B ne voit ni lots ni mouvements du tenant A.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/stock/batches')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->getJson($this->baseUrl().'/stock/movements')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->postJson($this->baseUrl().'/stock/adjustments', [
            'batch_id' => $batch->id,
            'quantity_delta' => -1,
            'reason' => 'Tentative cross-tenant',
        ])->assertStatus(404);

        // Solution inactive → 403 fail-closed.
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);
        $this->getJson($this->baseUrl().'/alerts')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }
}

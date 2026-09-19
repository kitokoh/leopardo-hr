<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Application\Services\PharmacyStockService;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Ventes comptoir (POS) — PHARMA-005 (#7802).
 *
 * Couvre : vente multi-lignes avec numéro VT-YYYY-XXXXX séquencé, totaux
 * calculés serveur et prix figés, délivrance FEFO (mouvements `sale` sur
 * les lots les plus proches de la péremption), produit à ordonnance sans
 * `prescription_id` → 422 PHARMACY_PRESCRIPTION_REQUIRED, stock insuffisant
 * → 422 PHARMACY_INSUFFICIENT_STOCK sans effet partiel, void (raison
 * obligatoire, manager, re-crédit des lots d'origine, vente conservée),
 * filtres de liste, isolation tenant et solution inactive → 403.
 */
class PharmacySaleTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $managerA;

    private Employee $lambdaA;

    private Employee $managerB;

    private PharmacyProduct $paracetamol;

    private PharmacyProduct $antibiotic;

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

        /** @var PharmacyProduct $paracetamol */
        $paracetamol = PharmacyProduct::query()->forceCreate([
            'company_id' => (string) $companyA->id,
            'name' => 'Doliprane 1000',
            'category' => 'medicament',
            'unit' => 'boite',
            'sale_price' => '120.50',
            'tax_rate' => '9.00',
            'status' => 'active',
        ]);
        $this->paracetamol = $paracetamol;

        /** @var PharmacyProduct $antibiotic */
        $antibiotic = PharmacyProduct::query()->forceCreate([
            'company_id' => (string) $companyA->id,
            'name' => 'Amoxicilline 500',
            'category' => 'medicament',
            'unit' => 'boite',
            'sale_price' => '300.00',
            'prescription_required' => true,
            'status' => 'active',
        ]);
        $this->antibiotic = $antibiotic;
    }

    private function stock(PharmacyProduct $product, string $number, string $expiry, int $quantity): PharmacyBatch
    {
        /** @var PharmacyStockService $service */
        $service = app(PharmacyStockService::class);

        return $service->receive(
            product: $product,
            batchNumber: $number,
            expiryDate: Carbon::parse($expiry),
            quantity: $quantity,
        );
    }

    public function test_sale_computes_totals_server_side_and_dispenses_fefo(): void
    {
        $late = $this->stock($this->paracetamol, 'LOT-LATE', Carbon::today()->addYear()->toDateString(), 50);
        $soon = $this->stock($this->paracetamol, 'LOT-SOON', Carbon::today()->addMonth()->toDateString(), 2);

        Sanctum::actingAs($this->lambdaA);
        $response = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'customer_name' => 'Client comptoir',
            'total_amount' => '1.00', // ignoré : totaux serveur
            'lines' => [
                ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 3],
            ],
        ]);

        $year = Carbon::now()->year;
        $response->assertStatus(201)
            ->assertJsonPath('data.number', sprintf('VT-%d-00001', $year))
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_amount', '361.50')
            ->assertJsonPath('data.lines.0.unit_price', '120.50')
            ->assertJsonPath('data.lines.0.tax_rate', '9.00')
            ->assertJsonPath('data.lines.0.line_total', '361.50');

        // FEFO : le lot périmant le plus tôt est consommé d'abord.
        $this->assertSame(0, $soon->refresh()->quantity);
        $this->assertSame(49, $late->refresh()->quantity);

        $saleId = $response->json('data.id');
        $this->assertIsInt($saleId);
        $this->assertSame(2, PharmacyStockMovement::query()
            ->where('reference_type', 'pharmacy_sale')
            ->where('reference_id', $saleId)
            ->where('type', 'sale')
            ->count());
    }

    public function test_prescription_product_requires_prescription_id(): void
    {
        $this->stock($this->antibiotic, 'LOT-AB', Carbon::today()->addYear()->toDateString(), 10);

        Sanctum::actingAs($this->lambdaA);

        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'card',
            'lines' => [
                ['product_id' => $this->antibiotic->getAttribute('id'), 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'PHARMACY_PRESCRIPTION_REQUIRED');

        // Aucun effet : pas de vente, pas de mouvement.
        $this->assertSame(0, PharmacySale::query()->count());

        // Avec référence d'ordonnance, la vente passe.
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'card',
            'prescription_id' => 4242,
            'lines' => [
                ['product_id' => $this->antibiotic->getAttribute('id'), 'quantity' => 1],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.prescription_id', 4242);
    }

    public function test_insufficient_stock_rejects_sale_atomically(): void
    {
        $this->stock($this->paracetamol, 'LOT-A', Carbon::today()->addYear()->toDateString(), 10);
        $this->stock($this->antibiotic, 'LOT-AB', Carbon::today()->addYear()->toDateString(), 1);

        Sanctum::actingAs($this->lambdaA);

        // La 2e ligne dépasse le stock : AUCUNE ligne ne doit être délivrée.
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'prescription_id' => 77,
            'lines' => [
                ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 5],
                ['product_id' => $this->antibiotic->getAttribute('id'), 'quantity' => 3],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'PHARMACY_INSUFFICIENT_STOCK');

        $this->assertSame(0, PharmacySale::query()->count());
        $this->assertSame(10, (int) PharmacyBatch::query()->where('batch_number', 'LOT-A')->firstOrFail()->quantity);
        $this->assertSame(0, PharmacyStockMovement::query()->where('type', 'sale')->count());
    }

    public function test_void_requires_manager_and_reason_and_recredits_original_batches(): void
    {
        $soon = $this->stock($this->paracetamol, 'LOT-SOON', Carbon::today()->addMonth()->toDateString(), 2);
        $late = $this->stock($this->paracetamol, 'LOT-LATE', Carbon::today()->addYear()->toDateString(), 10);

        Sanctum::actingAs($this->lambdaA);
        $sale = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [
                ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 5],
            ],
        ]);
        $sale->assertStatus(201);
        $saleId = $sale->json('data.id');
        $this->assertIsInt($saleId);
        $this->assertSame(0, $soon->refresh()->quantity);
        $this->assertSame(7, $late->refresh()->quantity);

        // Employé lambda : void interdit.
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'Erreur de caisse'])
            ->assertStatus(403);

        // Manager sans raison : 422.
        Sanctum::actingAs($this->managerA);
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', [])->assertStatus(422);

        // Void : lots d'origine re-crédités, vente conservée `voided`.
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'Erreur de caisse'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.void_reason', 'Erreur de caisse');

        $this->assertSame(2, $soon->refresh()->quantity);
        $this->assertSame(10, $late->refresh()->quantity);
        $this->assertSame(1, PharmacySale::query()->count());
        $this->assertSame(2, PharmacyStockMovement::query()
            ->where('reference_type', 'pharmacy_sale')
            ->where('reference_id', $saleId)
            ->where('type', 'return')
            ->count());

        // Un second void est refusé.
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'Encore'])->assertStatus(422);
    }

    public function test_sales_list_filters_by_payment_method(): void
    {
        $this->stock($this->paracetamol, 'LOT-A', Carbon::today()->addYear()->toDateString(), 20);

        Sanctum::actingAs($this->lambdaA);
        foreach (['cash', 'card'] as $method) {
            $this->postJson($this->baseUrl().'/sales', [
                'payment_method' => $method,
                'lines' => [
                    ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 1],
                ],
            ])->assertStatus(201);
        }

        $this->getJson($this->baseUrl().'/sales')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->getJson($this->baseUrl().'/sales?payment_method=card')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_method', 'card');
    }

    public function test_sales_are_tenant_isolated_and_fail_closed(): void
    {
        $this->stock($this->paracetamol, 'LOT-A', Carbon::today()->addYear()->toDateString(), 5);

        Sanctum::actingAs($this->lambdaA);
        $sale = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [
                ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 1],
            ],
        ]);
        $saleId = $sale->json('data.id');
        $this->assertIsInt($saleId);

        // Autre tenant : 404 sur détail et void, liste vide, produit invisible.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/sales/'.$saleId)->assertStatus(404);
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'x'])->assertStatus(404);
        $this->getJson($this->baseUrl().'/sales')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [
                ['product_id' => $this->paracetamol->getAttribute('id'), 'quantity' => 1],
            ],
        ])->assertStatus(422);

        // Solution inactive → 403 fail-closed.
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $inactive->id]);
        Sanctum::actingAs($employee);
        $this->getJson($this->baseUrl().'/sales')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }
}

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
 * Ventes comptoir — PHARMA-005 (#7802).
 *
 * Couvre : numéro VT-YYYY-XXXXX séquencé/tenant, totaux serveur avec prix
 * figés, FEFO à la vente, ordonnance exigée (422
 * PHARMACY_PRESCRIPTION_REQUIRED, y compris produits contrôlés), stock
 * insuffisant 422 sans effet partiel, void (raison obligatoire, manager,
 * restock par mouvements `return`, vente conservée), double void 422,
 * filtres, isolation tenant, solution inactive 403.
 */
class PharmacySaleTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

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
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['pharmacy' => true],
        ]);
        $this->companyB = $companyB;

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
        $paracetamol = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Paracétamol 500mg',
            'sale_price' => '50.00',
            'tax_rate' => '9.00',
        ]);
        $this->paracetamol = $paracetamol;

        /** @var PharmacyProduct $antibiotic */
        $antibiotic = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Amoxicilline 500mg',
            'sale_price' => '380.00',
            'prescription_required' => true,
        ]);
        $this->antibiotic = $antibiotic;

        $stock = app(PharmacyStockService::class);
        $stock->receive((string) $companyA->id, (int) $paracetamol->id, 'LOT-NEAR', Carbon::today()->addDays(30)->toDateString(), 10, '20.00');
        $stock->receive((string) $companyA->id, (int) $paracetamol->id, 'LOT-FAR', Carbon::today()->addDays(300)->toDateString(), 10, '22.00');
        $stock->receive((string) $companyA->id, (int) $antibiotic->id, 'LOT-AB', Carbon::today()->addDays(200)->toDateString(), 10, '250.00');
    }

    public function test_inactive_solution_gets_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $inactive->id]);
        Sanctum::actingAs($employee);

        $this->getJson($this->baseUrl().'/sales')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }

    public function test_sale_computes_totals_server_side_and_dispenses_fefo(): void
    {
        Sanctum::actingAs($this->lambdaA);
        $year = Carbon::now()->format('Y');

        $sale = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'customer_name' => 'Client comptoir',
            // Le client tente d'imposer un total : ignoré (totaux serveur).
            'total_amount' => '1.00',
            'lines' => [['product_id' => $this->paracetamol->id, 'quantity' => 12]],
        ])->assertStatus(201)
            ->assertJsonPath('data.number', 'VT-'.$year.'-00001')
            ->assertJsonPath('data.total_amount', '600.00')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.lines.0.unit_price', '50.00')
            ->assertJsonPath('data.lines.0.tax_rate', '9.00')
            ->assertJsonPath('data.lines.0.line_total', '600.00')
            ->json('data');

        // FEFO : le lot le plus proche (10) est épuisé, puis 2 sur le lointain.
        $near = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-NEAR')->firstOrFail();
        $far = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-FAR')->firstOrFail();
        $this->assertSame(0, $near->quantity);
        $this->assertSame(8, $far->quantity);

        // Mouvements `sale` liés à la vente.
        $this->assertSame(2, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'sale')
            ->where('reference_type', 'pharmacy_sale')
            ->where('reference_id', $sale['id'])
            ->count());

        // Numérotation séquencée.
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'card',
            'lines' => [['product_id' => $this->paracetamol->id, 'quantity' => 1]],
        ])->assertStatus(201)->assertJsonPath('data.number', 'VT-'.$year.'-00002');
    }

    public function test_prescription_required_product_needs_prescription_id(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [['product_id' => $this->antibiotic->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_PRESCRIPTION_REQUIRED');

        // Un produit contrôlé sans ordonnance est refusé de la même façon.
        /** @var PharmacyProduct $controlled */
        $controlled = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Morphine 10mg',
            'sale_price' => '900.00',
            'is_controlled' => true,
        ]);
        app(PharmacyStockService::class)->receive((string) $this->companyA->id, (int) $controlled->id, 'LOT-M', Carbon::today()->addYear()->toDateString(), 5, '500.00');

        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [['product_id' => $controlled->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_PRESCRIPTION_REQUIRED');

        // Avec une ordonnance du tenant → accepté ; ordonnance inexistante → 422.
        /** @var \App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber $prescriber */
        $prescriber = \App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'full_name' => 'Dr Amine Kaci',
        ]);
        /** @var \App\Modules\Pharmacy\Domain\Models\PharmacyPrescription $prescription */
        $prescription = \App\Modules\Pharmacy\Domain\Models\PharmacyPrescription::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'prescriber_id' => $prescriber->id,
            'patient_name' => 'Patient Test',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-0001',
        ]);

        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'prescription_id' => 999999,
            'lines' => [['product_id' => $this->antibiotic->id, 'quantity' => 2]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_PRESCRIPTION_NOT_FOUND');

        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'prescription_id' => $prescription->id,
            'lines' => [['product_id' => $this->antibiotic->id, 'quantity' => 2]],
        ])->assertStatus(201)->assertJsonPath('data.prescription_id', (int) $prescription->id);
    }

    public function test_insufficient_stock_rejects_sale_atomically(): void
    {
        Sanctum::actingAs($this->lambdaA);

        // 20 disponibles au total pour le paracétamol : 21 → refus.
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [
                ['product_id' => $this->paracetamol->id, 'quantity' => 21],
            ],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INSUFFICIENT_STOCK');

        // Aucune vente créée, stock intact, aucun mouvement `sale`.
        $this->assertSame(0, PharmacySale::withoutGlobalScopes()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(20, (int) PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('product_id', $this->paracetamol->id)
            ->sum('quantity'));
        $this->assertSame(0, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'sale')
            ->count());
    }

    public function test_void_restocks_original_batches_and_keeps_sale(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $saleId = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [['product_id' => $this->paracetamol->id, 'quantity' => 12]],
        ])->assertStatus(201)->json('data.id');

        // Void par un employé lambda → 403 (manager only).
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'erreur de caisse'])
            ->assertStatus(403);

        Sanctum::actingAs($this->managerA);

        // Raison obligatoire.
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'erreur de caisse'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.void_reason', 'erreur de caisse');

        // Stock ré-crédité sur les lots d'ORIGINE (10 + 2).
        $near = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-NEAR')->firstOrFail();
        $far = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-FAR')->firstOrFail();
        $this->assertSame(10, $near->quantity);
        $this->assertSame(10, $far->quantity);

        // Mouvements `return` tracés, vente conservée.
        $this->assertSame(2, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'return')
            ->where('reference_id', $saleId)
            ->count());
        $this->assertSame(1, PharmacySale::withoutGlobalScopes()->whereKey($saleId)->count());

        // Double void → 422 transition invalide.
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'encore'])
            ->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INVALID_TRANSITION');
    }

    public function test_sales_filters_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->lambdaA);
        $saleId = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'card',
            'lines' => [['product_id' => $this->paracetamol->id, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        Sanctum::actingAs($this->managerA);
        $this->getJson($this->baseUrl().'/sales?payment_method=card')
            ->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson($this->baseUrl().'/sales?payment_method=mobile')
            ->assertStatus(200)->assertJsonPath('meta.total', 0);
        $this->getJson($this->baseUrl().'/sales?from='.Carbon::today()->addDay()->toDateString())
            ->assertStatus(200)->assertJsonPath('meta.total', 0);

        // Cross-tenant : la vente du tenant A est invisible pour B.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/sales/'.$saleId)->assertStatus(404);
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'vol'])->assertStatus(404);
        $this->getJson($this->baseUrl().'/sales')->assertStatus(200)->assertJsonPath('meta.total', 0);

        // Produit du tenant A invendable par le tenant B (404).
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [['product_id' => $this->paracetamol->id, 'quantity' => 1]],
        ])->assertStatus(404);
    }
}

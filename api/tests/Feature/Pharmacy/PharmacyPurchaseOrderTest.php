<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Achats d'officine — PHARMA-004 (#7801).
 *
 * Couvre : CRUD fournisseurs (RBAC manager, isolation tenant), création de
 * commande (numéro PO-YYYY-XXXX séquencé par tenant), transitions valides et
 * invalides (422 PHARMACY_INVALID_TRANSITION), réception partielle puis
 * totale → lots + mouvements `receipt`, sur-réception refusée (422
 * PHARMACY_OVER_RECEIPT), solution inactive 403.
 */
class PharmacyPurchaseOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $managerB;

    private PharmacyProduct $productA;

    private PharmacySupplier $supplierA;

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
            'name' => 'Amoxicilline 500mg',
        ]);
        $this->productA = $productA;

        /** @var PharmacySupplier $supplierA */
        $supplierA = PharmacySupplier::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Grossiste Central',
            'type' => 'wholesaler',
        ]);
        $this->supplierA = $supplierA;
    }

    public function test_inactive_solution_gets_403(): void
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

        $this->getJson($this->baseUrl().'/suppliers')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/purchase-orders')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }

    public function test_supplier_crud_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->managerA);

        $supplierId = $this->postJson($this->baseUrl().'/suppliers', [
            'name' => 'Laboratoire Sud',
            'type' => 'laboratory',
            'email' => 'contact@lab-sud.dz',
        ])->assertStatus(201)
            ->assertJsonPath('data.type', 'laboratory')
            ->json('data.id');

        $this->putJson($this->baseUrl().'/suppliers/'.$supplierId, ['status' => 'archived'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        $this->getJson($this->baseUrl().'/suppliers?status=archived')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Cross-tenant : invisible et non modifiable.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/suppliers/'.$supplierId)->assertStatus(404);
        $this->putJson($this->baseUrl().'/suppliers/'.$supplierId, ['name' => 'Vol'])->assertStatus(404);
        $this->getJson($this->baseUrl().'/suppliers')->assertStatus(200)->assertJsonPath('meta.total', 0);
    }

    public function test_purchase_order_numbers_are_sequenced_per_tenant(): void
    {
        Sanctum::actingAs($this->managerA);
        $year = Carbon::now()->format('Y');

        $first = $this->createDraftOrder(5);
        $second = $this->createDraftOrder(3);

        $this->assertSame('PO-'.$year.'-0001', $first['number']);
        $this->assertSame('PO-'.$year.'-0002', $second['number']);

        // Le tenant B repart à 0001 (séquence par tenant).
        Sanctum::actingAs($this->managerB);
        /** @var PharmacyProduct $productB */
        $productB = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Produit B',
        ]);
        /** @var PharmacySupplier $supplierB */
        $supplierB = PharmacySupplier::withoutGlobalScopes()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Grossiste B',
        ]);

        $orderB = $this->postJson($this->baseUrl().'/purchase-orders', [
            'supplier_id' => $supplierB->id,
            'lines' => [['product_id' => $productB->id, 'quantity_ordered' => 2]],
        ])->assertStatus(201)->json('data');

        $this->assertSame('PO-'.$year.'-0001', $orderB['number']);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        Sanctum::actingAs($this->managerA);
        $order = $this->createDraftOrder(5);

        // Réception d'un draft → 422.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/receive', [
            'lines' => [[
                'line_id' => $order['lines'][0]['id'],
                'quantity' => 1,
                'batch_number' => 'LOT-1',
                'expiry_date' => Carbon::today()->addYear()->toDateString(),
            ]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INVALID_TRANSITION');

        // draft → ordered OK ; re-place → 422.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/place')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ordered');
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/place')
            ->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INVALID_TRANSITION');

        // Réception totale puis annulation d'un received → 422.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/receive', [
            'lines' => [[
                'line_id' => $order['lines'][0]['id'],
                'quantity' => 5,
                'batch_number' => 'LOT-1',
                'expiry_date' => Carbon::today()->addYear()->toDateString(),
            ]],
        ])->assertStatus(200)->assertJsonPath('data.status', 'received');

        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/cancel')
            ->assertStatus(422)->assertJsonPath('error', 'PHARMACY_INVALID_TRANSITION');
    }

    public function test_partial_then_full_receipt_creates_batches_and_movements(): void
    {
        Sanctum::actingAs($this->managerA);
        $order = $this->createDraftOrder(10, '120.00');
        $lineId = $order['lines'][0]['id'];
        $expiry = Carbon::today()->addYear()->toDateString();

        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/place')->assertStatus(200);

        // Réception partielle : 4/10 → partially_received.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/receive', [
            'lines' => [['line_id' => $lineId, 'quantity' => 4, 'batch_number' => 'LOT-P1', 'expiry_date' => $expiry, 'unit_cost' => '118.00']],
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_received')
            ->assertJsonPath('data.lines.0.quantity_received', 4);

        // Sur-réception refusée : 4 reçus + 7 > 10 commandés.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/receive', [
            'lines' => [['line_id' => $lineId, 'quantity' => 7, 'batch_number' => 'LOT-P2', 'expiry_date' => $expiry]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_OVER_RECEIPT');

        // Solde exact : 6 → received.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/receive', [
            'lines' => [['line_id' => $lineId, 'quantity' => 6, 'batch_number' => 'LOT-P2', 'expiry_date' => $expiry]],
        ])->assertStatus(200)->assertJsonPath('data.status', 'received');

        // Traçabilité complète : 2 lots créés, 2 mouvements receipt liés au PO.
        $this->assertSame(2, PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('product_id', $this->productA->id)
            ->count());
        $this->assertSame(10, (int) PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->sum('quantity'));
        $this->assertSame(2, PharmacyStockMovement::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'receipt')
            ->where('reference_type', 'purchase_order')
            ->where('reference_id', $order['id'])
            ->count());
    }

    public function test_cross_tenant_purchase_order_is_invisible(): void
    {
        Sanctum::actingAs($this->managerA);
        $order = $this->createDraftOrder(5);

        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/purchase-orders/'.$order['id'])->assertStatus(404);
        $this->postJson($this->baseUrl().'/purchase-orders/'.$order['id'].'/place')->assertStatus(404);

        // Fournisseur d'un autre tenant → 404 à la création.
        $this->postJson($this->baseUrl().'/purchase-orders', [
            'supplier_id' => $this->supplierA->id,
            'lines' => [['product_id' => $this->productA->id, 'quantity_ordered' => 1]],
        ])->assertStatus(404);
    }

    /**
     * @return array{id: int, number: string, lines: list<array{id: int}>}
     */
    private function createDraftOrder(int $quantity, string $unitPrice = '100.00'): array
    {
        /** @var array{id: int, number: string, lines: list<array{id: int}>} $data */
        $data = $this->postJson($this->baseUrl().'/purchase-orders', [
            'supplier_id' => $this->supplierA->id,
            'notes' => 'réassort hebdomadaire',
            'lines' => [[
                'product_id' => $this->productA->id,
                'quantity_ordered' => $quantity,
                'unit_price' => $unitPrice,
            ]],
        ])->assertStatus(201)->assertJsonPath('data.status', 'draft')->json('data');

        return $data;
    }
}

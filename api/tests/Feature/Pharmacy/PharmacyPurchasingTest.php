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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Fournisseurs et commandes d'achat d'officine — PHARMA-004 (#7801).
 *
 * Couvre : CRUD fournisseur (RBAC manager, archivage), création de commande
 * `draft` avec numéro PO-YYYY-XXXX séquencé par tenant, transitions d'état
 * (réception d'un draft refusée, annulation d'un received refusée),
 * réception partielle → `partially_received` puis totale → `received` avec
 * création des lots + mouvements `receipt`, sur-réception refusée,
 * isolation tenant et solution inactive → 403.
 */
class PharmacyPurchasingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $managerA;

    private Employee $lambdaA;

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
            'name' => 'Amoxicilline 500',
            'category' => 'medicament',
            'unit' => 'boite',
            'purchase_price' => '250.00',
            'status' => 'active',
        ]);
        $this->productA = $productA;

        /** @var PharmacySupplier $supplierA */
        $supplierA = PharmacySupplier::query()->forceCreate([
            'company_id' => (string) $companyA->id,
            'name' => 'Grossiste Central',
            'type' => 'wholesaler',
            'status' => 'active',
        ]);
        $this->supplierA = $supplierA;
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function createDraftOrder(int $quantity = 10): TestResponse
    {
        Sanctum::actingAs($this->managerA);

        return $this->postJson($this->baseUrl().'/purchase-orders', [
            'supplier_id' => $this->supplierA->id,
            'lines' => [
                ['product_id' => $this->productA->getAttribute('id'), 'quantity_ordered' => $quantity],
            ],
        ]);
    }

    public function test_supplier_crud_requires_manager_and_supports_archive(): void
    {
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/suppliers', ['name' => 'Labo X'])->assertStatus(403);

        Sanctum::actingAs($this->managerA);
        $created = $this->postJson($this->baseUrl().'/suppliers', [
            'name' => 'Laboratoire Atlas',
            'type' => 'laboratory',
            'email' => 'contact@atlas.example',
        ]);
        $created->assertStatus(201)->assertJsonPath('data.type', 'laboratory');

        $id = $created->json('data.id');
        $this->assertIsInt($id);

        $this->putJson($this->baseUrl().'/suppliers/'.$id, ['contact_name' => 'Dr Slimani'])
            ->assertStatus(200)
            ->assertJsonPath('data.contact_name', 'Dr Slimani');

        $this->patchJson($this->baseUrl().'/suppliers/'.$id.'/archive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Lecture pour tout employé du tenant.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/suppliers?type=laboratory')->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_purchase_order_numbers_are_sequenced_per_tenant(): void
    {
        $first = $this->createDraftOrder();
        $second = $this->createDraftOrder();

        $year = Carbon::now()->year;
        $first->assertStatus(201)->assertJsonPath('data.number', sprintf('PO-%d-0001', $year));
        $second->assertStatus(201)->assertJsonPath('data.number', sprintf('PO-%d-0002', $year));
        $second->assertJsonPath('data.status', 'draft');
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $order = $this->createDraftOrder();
        $id = $order->json('data.id');
        $this->assertIsInt($id);

        // Réception d'un draft → 422.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/receive', [
            'receipts' => [[
                'line_id' => $order->json('data.lines.0.id'),
                'quantity' => 1,
                'batch_number' => 'LOT-1',
                'expiry_date' => Carbon::today()->addYear()->toDateString(),
            ]],
        ])->assertStatus(422);

        // draft → ordered OK ; re-passage → 422.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(200)->assertJsonPath('data.status', 'ordered');
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(422);
    }

    public function test_partial_then_full_receipt_creates_batches_and_movements(): void
    {
        $order = $this->createDraftOrder(10);
        $id = $order->json('data.id');
        $lineId = $order->json('data.lines.0.id');
        $this->assertIsInt($id);
        $this->assertIsInt($lineId);

        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(200);

        $expiry = Carbon::today()->addYear()->toDateString();

        // Réception partielle (6/10) → partially_received.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/receive', [
            'receipts' => [[
                'line_id' => $lineId,
                'quantity' => 6,
                'batch_number' => 'LOT-A1',
                'expiry_date' => $expiry,
            ]],
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_received')
            ->assertJsonPath('data.lines.0.quantity_received', 6);

        /** @var PharmacyBatch $batch */
        $batch = PharmacyBatch::query()->where('batch_number', 'LOT-A1')->firstOrFail();
        $this->assertSame(6, $batch->quantity);
        $this->assertSame((int) $this->supplierA->id, $batch->supplier_id);

        $this->assertTrue(
            PharmacyStockMovement::query()
                ->where('batch_id', $batch->id)
                ->where('type', 'receipt')
                ->where('quantity_delta', 6)
                ->where('reference_type', 'pharmacy_purchase_order')
                ->where('reference_id', $id)
                ->exists()
        );

        // Solde (4/10) → received, annulation désormais refusée.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/receive', [
            'receipts' => [[
                'line_id' => $lineId,
                'quantity' => 4,
                'batch_number' => 'LOT-A2',
                'expiry_date' => $expiry,
            ]],
        ])->assertStatus(200)->assertJsonPath('data.status', 'received');

        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/cancel')->assertStatus(422);
    }

    public function test_over_receipt_is_refused_without_side_effects(): void
    {
        $order = $this->createDraftOrder(5);
        $id = $order->json('data.id');
        $lineId = $order->json('data.lines.0.id');
        $this->assertIsInt($id);
        $this->assertIsInt($lineId);

        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(200);

        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/receive', [
            'receipts' => [[
                'line_id' => $lineId,
                'quantity' => 6,
                'batch_number' => 'LOT-OVER',
                'expiry_date' => Carbon::today()->addYear()->toDateString(),
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PharmacyBatch::query()->where('batch_number', 'LOT-OVER')->count());
        $this->getJson($this->baseUrl().'/purchase-orders/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ordered')
            ->assertJsonPath('data.lines.0.quantity_received', 0);
    }

    public function test_cancel_is_allowed_for_draft_and_ordered_only(): void
    {
        $order = $this->createDraftOrder();
        $id = $order->json('data.id');
        $this->assertIsInt($id);

        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Une commande annulée ne se passe plus.
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(422);
    }

    public function test_purchasing_is_tenant_isolated_and_fail_closed(): void
    {
        $order = $this->createDraftOrder();
        $id = $order->json('data.id');
        $this->assertIsInt($id);

        // Manager d'un autre tenant : 404 sur détail et transitions, liste vide.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/purchase-orders/'.$id)->assertStatus(404);
        $this->postJson($this->baseUrl().'/purchase-orders/'.$id.'/order')->assertStatus(404);
        $this->getJson($this->baseUrl().'/suppliers')->assertStatus(200)->assertJsonCount(0, 'data');

        // Fournisseur d'un autre tenant introuvable à la création.
        $this->postJson($this->baseUrl().'/purchase-orders', [
            'supplier_id' => $this->supplierA->id,
            'lines' => [
                ['product_id' => $this->productA->getAttribute('id'), 'quantity_ordered' => 1],
            ],
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
        $this->getJson($this->baseUrl().'/purchase-orders')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }
}

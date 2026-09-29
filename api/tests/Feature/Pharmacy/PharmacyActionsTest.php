<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Application\Actions\AdjustPharmacyStockAction;
use App\Modules\Pharmacy\Application\Actions\ArchivePharmacyProductAction;
use App\Modules\Pharmacy\Application\Actions\CancelPharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacyProductAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\PlacePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\ReceivePharmacyPurchaseOrderAction;
use App\Modules\Pharmacy\Application\Actions\RecordPharmacySaleAction;
use App\Modules\Pharmacy\Application\Actions\RegisterPharmacyPrescriberAction;
use App\Modules\Pharmacy\Application\Actions\RegisterPharmacyPrescriptionAction;
use App\Modules\Pharmacy\Application\Actions\RegisterPharmacySupplierAction;
use App\Modules\Pharmacy\Application\Actions\UpdatePharmacyPrescriberAction;
use App\Modules\Pharmacy\Application\Actions\UpdatePharmacyPrescriptionAction;
use App\Modules\Pharmacy\Application\Actions\UpdatePharmacyProductAction;
use App\Modules\Pharmacy\Application\Actions\UpdatePharmacySupplierAction;
use App\Modules\Pharmacy\Application\Actions\VoidPharmacySaleAction;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyPrescriptionRequiredException;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyStockService;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Actions de la couche Application Pharmacy — BOS-024c (#8214).
 *
 * Les cas d'usage d'écriture du module doivent être invocables directement
 * (hors HTTP) avec un tenant dérivé de l'acteur authentifié, et produire le
 * même résultat métier que via les contrôleurs amincis (délégation aux
 * services d'infrastructure éprouvés — FEFO et ordonnancier inchangés).
 */
class PharmacyActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $managerA;

    private Employee $lambdaA;

    private PharmacyProduct $paracetamol;

    private PharmacyProduct $antibiotic;

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

    public function test_record_sale_action_creates_sale_with_frozen_prices_and_fefo(): void
    {
        $sale = app(RecordPharmacySaleAction::class)->execute($this->lambdaA, [
            'payment_method' => 'cash',
            'lines' => [['product_id' => (int) $this->paracetamol->id, 'quantity' => 3]],
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $sale->company_id);
        $this->assertStringStartsWith('VT-', $sale->number);
        $this->assertSame('completed', $sale->status);
        $this->assertSame('150.00', (string) $sale->total_amount);
        $this->assertSame((int) $this->lambdaA->id, (int) $sale->sold_by_employee_id);

        // FEFO : le lot le plus proche de la péremption est décrémenté d'abord.
        /** @var PharmacyBatch $near */
        $near = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-NEAR')->firstOrFail();
        $this->assertSame(7, (int) $near->quantity);

        /** @var PharmacyBatch $far */
        $far = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-FAR')->firstOrFail();
        $this->assertSame(10, (int) $far->quantity);

        $this->assertSame(3, (int) $sale->lines()->sum('quantity'));
    }

    public function test_record_sale_action_enforces_prescription_rule(): void
    {
        $this->expectException(PharmacyPrescriptionRequiredException::class);

        app(RecordPharmacySaleAction::class)->execute($this->lambdaA, [
            'payment_method' => 'cash',
            'lines' => [['product_id' => (int) $this->antibiotic->id, 'quantity' => 1]],
        ]);
    }

    public function test_void_sale_action_keeps_sale_and_restocks_via_return_movements(): void
    {
        $sale = app(RecordPharmacySaleAction::class)->execute($this->lambdaA, [
            'payment_method' => 'cash',
            'lines' => [['product_id' => (int) $this->paracetamol->id, 'quantity' => 2]],
        ]);

        $voided = app(VoidPharmacySaleAction::class)->execute($sale, 'Erreur de caisse', $this->managerA);

        $this->assertSame('voided', $voided->status);
        $this->assertSame('Erreur de caisse', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);

        // Restock : le lot d'origine est ré-crédité par mouvements `return`.
        /** @var PharmacyBatch $near */
        $near = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-NEAR')->firstOrFail();
        $this->assertSame(10, (int) $near->quantity);

        $this->assertTrue(
            PharmacyStockMovement::withoutGlobalScopes()
                ->where('company_id', $this->companyA->id)
                ->where('type', 'return')
                ->exists()
        );
    }

    public function test_create_purchase_order_action_creates_draft_with_sequenced_number(): void
    {
        $supplier = $this->makeSupplier();

        $order = app(CreatePharmacyPurchaseOrderAction::class)->execute($this->managerA, $supplier, [
            'supplier_id' => (int) $supplier->id,
            'lines' => [['product_id' => (int) $this->paracetamol->id, 'quantity_ordered' => 5]],
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $order->company_id);
        $this->assertSame('draft', $order->status);
        $this->assertStringStartsWith('PO-'.Carbon::now()->format('Y').'-', $order->number);
        $this->assertSame((int) $supplier->id, (int) $order->supplier_id);
        $this->assertSame(1, $order->lines()->count());
        $this->assertSame(0, (int) $order->lines()->firstOrFail()->quantity_received);
    }

    public function test_place_purchase_order_action_transitions_draft_to_ordered(): void
    {
        $order = $this->makeOrder();

        $placed = app(PlacePharmacyPurchaseOrderAction::class)->execute($order);

        $this->assertSame('ordered', $placed->status);
        $this->assertNotNull($placed->ordered_at);
    }

    public function test_receive_purchase_order_action_creates_batches_and_receipt_movements(): void
    {
        $order = $this->makeOrder();
        app(PlacePharmacyPurchaseOrderAction::class)->execute($order);

        /** @var \App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrderLine $line */
        $line = $order->lines()->firstOrFail();

        $received = app(ReceivePharmacyPurchaseOrderAction::class)->execute($order, [
            'lines' => [[
                'line_id' => (int) $line->id,
                'quantity' => 5,
                'batch_number' => 'LOT-PO-1',
                'expiry_date' => Carbon::today()->addDays(180)->toDateString(),
            ]],
        ], $this->managerA);

        $this->assertSame('received', $received->status);
        $this->assertNotNull($received->received_at);
        $this->assertSame(5, (int) $line->refresh()->quantity_received);

        $this->assertTrue(
            PharmacyBatch::withoutGlobalScopes()
                ->where('company_id', $this->companyA->id)
                ->where('batch_number', 'LOT-PO-1')
                ->where('quantity', 5)
                ->exists()
        );

        $this->assertTrue(
            PharmacyStockMovement::withoutGlobalScopes()
                ->where('company_id', $this->companyA->id)
                ->where('type', 'receipt')
                ->where('reference_type', 'purchase_order')
                ->where('reference_id', (int) $order->id)
                ->exists()
        );
    }

    public function test_cancel_purchase_order_action_from_ordered(): void
    {
        $order = $this->makeOrder();
        app(PlacePharmacyPurchaseOrderAction::class)->execute($order);

        $cancelled = app(CancelPharmacyPurchaseOrderAction::class)->execute($order);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_adjust_stock_action_applies_signed_delta_with_reason(): void
    {
        /** @var PharmacyBatch $far */
        $far = PharmacyBatch::withoutGlobalScopes()->where('batch_number', 'LOT-FAR')->firstOrFail();

        $adjusted = app(AdjustPharmacyStockAction::class)->execute($this->managerA, [
            'batch_id' => (int) $far->id,
            'quantity_delta' => -4,
            'reason' => 'Inventaire tournant',
        ]);

        $this->assertSame(6, (int) $adjusted->quantity);

        $this->assertTrue(
            PharmacyStockMovement::withoutGlobalScopes()
                ->where('company_id', $this->companyA->id)
                ->where('batch_id', (int) $far->id)
                ->where('type', 'adjustment')
                ->where('quantity_delta', -4)
                ->exists()
        );
    }

    public function test_register_prescription_action_scopes_company_from_actor(): void
    {
        $prescriber = $this->makePrescriber();

        $prescription = app(RegisterPharmacyPrescriptionAction::class)->execute($this->lambdaA, [
            'prescriber_id' => (int) $prescriber->id,
            'patient_name' => 'Amina B.',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-2026-0001',
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $prescription->company_id);
        $this->assertSame((int) $prescriber->id, (int) $prescription->prescriber_id);
        $this->assertSame('Amina B.', $prescription->patient_name);
    }

    public function test_update_prescription_action_applies_corrections(): void
    {
        $prescription = $this->makePrescription();

        $updated = app(UpdatePharmacyPrescriptionAction::class)->execute($prescription, [
            'patient_name' => 'Amina Benali',
            'notes' => 'Renouvellement 3 mois',
        ]);

        $this->assertSame('Amina Benali', $updated->patient_name);
        $this->assertSame('Renouvellement 3 mois', $updated->notes);
    }

    public function test_create_product_action_applies_catalog_defaults(): void
    {
        $product = app(CreatePharmacyProductAction::class)->execute($this->managerA, [
            'name' => 'Ibuprofène 400mg',
            'sale_price' => '120.00',
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $product->company_id);
        $this->assertSame('medicament', $product->category);
        $this->assertSame('unite', $product->unit);
        $this->assertSame('active', $product->status);
        $this->assertFalse((bool) $product->prescription_required);
        $this->assertFalse((bool) $product->is_controlled);
    }

    public function test_update_product_action_applies_changes(): void
    {
        $updated = app(UpdatePharmacyProductAction::class)->execute($this->paracetamol, [
            'sale_price' => '55.00',
            'min_stock_level' => 12,
        ]);

        $this->assertSame('55.00', (string) $updated->sale_price);
        $this->assertSame(12, (int) $updated->min_stock_level);
    }

    public function test_archive_product_action_sets_archived_status(): void
    {
        $archived = app(ArchivePharmacyProductAction::class)->execute($this->paracetamol);

        $this->assertSame('archived', $archived->status);
    }

    public function test_register_supplier_action_applies_defaults(): void
    {
        $supplier = app(RegisterPharmacySupplierAction::class)->execute($this->managerA, [
            'name' => 'Biopharm Distribution',
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $supplier->company_id);
        $this->assertSame('wholesaler', $supplier->type);
        $this->assertSame('active', $supplier->status);
    }

    public function test_update_supplier_action_applies_changes(): void
    {
        $supplier = $this->makeSupplier();

        $updated = app(UpdatePharmacySupplierAction::class)->execute($supplier, [
            'contact_name' => 'Karim H.',
            'phone' => '+2135550000',
        ]);

        $this->assertSame('Karim H.', $updated->contact_name);
        $this->assertSame('+2135550000', $updated->phone);
    }

    public function test_register_prescriber_action_applies_default_status(): void
    {
        $prescriber = app(RegisterPharmacyPrescriberAction::class)->execute($this->managerA, [
            'full_name' => 'Dr Leïla Mansouri',
            'registration_number' => 'ORD-ALG-12345',
        ]);

        $this->assertSame((string) $this->companyA->id, (string) $prescriber->company_id);
        $this->assertSame('active', $prescriber->status);
    }

    public function test_update_prescriber_action_applies_changes_and_archiving(): void
    {
        $prescriber = $this->makePrescriber();

        $updated = app(UpdatePharmacyPrescriberAction::class)->execute($prescriber, [
            'specialty' => 'Généraliste',
            'status' => 'archived',
        ]);

        $this->assertSame('Généraliste', $updated->specialty);
        $this->assertSame('archived', $updated->status);
    }

    private function makeSupplier(): PharmacySupplier
    {
        return app(RegisterPharmacySupplierAction::class)->execute($this->managerA, [
            'name' => 'Sarl Medis',
        ]);
    }

    private function makePrescriber(): PharmacyPrescriber
    {
        return app(RegisterPharmacyPrescriberAction::class)->execute($this->managerA, [
            'full_name' => 'Dr Leïla Mansouri',
            'registration_number' => 'ORD-ALG-12345',
        ]);
    }

    private function makePrescription(): PharmacyPrescription
    {
        return app(RegisterPharmacyPrescriptionAction::class)->execute($this->lambdaA, [
            'prescriber_id' => (int) $this->makePrescriber()->id,
            'patient_name' => 'Amina B.',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-TEST-0001',
        ]);
    }

    private function makeOrder(): PharmacyPurchaseOrder
    {
        $supplier = $this->makeSupplier();

        return app(CreatePharmacyPurchaseOrderAction::class)->execute($this->managerA, $supplier, [
            'supplier_id' => (int) $supplier->id,
            'lines' => [['product_id' => (int) $this->paracetamol->id, 'quantity_ordered' => 5]],
        ]);
    }
}

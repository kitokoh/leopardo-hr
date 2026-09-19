<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7813) — reçus de caisse et factures PDF des commandes.
 *
 * Couvre : reçu JSON structuré + variante PDF (`?format=pdf`), facture PDF
 * avec numérotation légale par tenant (FAC-YYYY-NNNNNN, attribué à la
 * première génération puis STABLE), commande non complétée → 422, isolation
 * cross-tenant → 404 fail-closed, RBAC lecture membres du tenant, gate
 * feature flag retail, indépendance des séquences entre tenants.
 */
class RetailReceiptInvoiceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

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
     * Crée une commande POS du tenant de l'acteur : emplacement, produit
     * publié, session ouverte, commande à 2 unités (3 000 minor). Si
     * `$complete`, encaisse le total (commande `completed`).
     *
     * @return array<string, mixed>
     */
    private function makeOrder(Employee $principal, bool $complete = true, string $suffix = 'A'): array
    {
        $this->actingAsUser($principal);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique '.$suffix,
            'code' => 'STORE-'.$suffix,
        ])->assertStatus(201)->json('data');

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-'.$suffix,
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);

        $session = $this->postJson('/api/v1/retail/pos/sessions', [
            'location_id' => (int) $location['id'],
        ])->assertStatus(201)->json('data');

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        if ($complete) {
            $order = $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
                'method' => 'cash',
                'amount_minor' => 3_000,
            ])->assertStatus(201)->json('data');
        }

        return $order;
    }

    public function test_receipt_returns_structured_ticket_json(): void
    {
        $order = $this->makeOrder($this->principalA);

        // Lecture ouverte aux membres du tenant (policy view).
        $this->actingAsUser($this->employeeA);

        $receipt = $this->getJson("/api/v1/retail/pos/orders/{$order['id']}/receipt")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_minor', 3_000)
            ->json('data');

        $this->assertStringStartsWith('POS-', $receipt['reference']);
        $this->assertCount(1, $receipt['items']);
        $this->assertSame('Jus de bissap 50cl', $receipt['items'][0]['product_name']);
        $this->assertCount(1, $receipt['payments']);
        $this->assertSame('cash', $receipt['payments'][0]['method']);
        // Pas encore facturée : le numéro légal n'est jamais attribué par le reçu.
        $this->assertNull($receipt['invoice_number']);
    }

    public function test_receipt_pdf_variant_streams_a_pdf(): void
    {
        $order = $this->makeOrder($this->principalA);

        $response = $this->get("/api/v1/retail/pos/orders/{$order['id']}/receipt?format=pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');
    }

    public function test_receipt_is_isolated_cross_tenant(): void
    {
        $order = $this->makeOrder($this->principalA);

        $this->actingAsUser($this->principalB);

        $this->getJson("/api/v1/retail/pos/orders/{$order['id']}/receipt")
            ->assertStatus(404);
    }

    public function test_invoice_pdf_assigns_legal_number_once_then_stable(): void
    {
        $order = $this->makeOrder($this->principalA);

        $response = $this->get("/api/v1/retail/orders/{$order['id']}/invoice.pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');

        /** @var RetailOrder $invoiced */
        $invoiced = RetailOrder::query()
            ->where('company_id', $this->companyA->id)
            ->findOrFail((int) $order['id']);

        $year = now()->year;
        $this->assertSame("FAC-{$year}-000001", $invoiced->invoice_number);
        $this->assertNotNull($invoiced->invoiced_at);
        $firstInvoicedAt = $invoiced->invoiced_at->toIso8601String();

        // Deuxième génération : numéro et horodatage STABLES.
        $this->get("/api/v1/retail/orders/{$order['id']}/invoice.pdf")->assertStatus(200);

        $again = RetailOrder::query()
            ->where('company_id', $this->companyA->id)
            ->findOrFail((int) $order['id']);
        $this->assertSame("FAC-{$year}-000001", $again->invoice_number);
        $this->assertSame($firstInvoicedAt, $again->invoiced_at?->toIso8601String());

        // Le numéro apparaît aussi dans le payload du reçu/ticket.
        $this->getJson("/api/v1/retail/pos/orders/{$order['id']}/receipt")
            ->assertStatus(200)
            ->assertJsonPath('data.invoice_number', "FAC-{$year}-000001");
    }

    public function test_invoice_numbers_increment_per_tenant_and_are_independent_between_tenants(): void
    {
        $year = now()->year;

        $orderA1 = $this->makeOrder($this->principalA, true, 'A1');
        $orderA2 = $this->makeOrder($this->principalA, true, 'A2');
        $orderB1 = $this->makeOrder($this->principalB, true, 'B1');

        $this->actingAsUser($this->principalA);
        $this->get("/api/v1/retail/orders/{$orderA1['id']}/invoice.pdf")->assertStatus(200);
        $this->get("/api/v1/retail/orders/{$orderA2['id']}/invoice.pdf")->assertStatus(200);

        $this->actingAsUser($this->principalB);
        $this->get("/api/v1/retail/orders/{$orderB1['id']}/invoice.pdf")->assertStatus(200);

        $numberA1 = RetailOrder::query()->where('company_id', $this->companyA->id)
            ->findOrFail((int) $orderA1['id'])->invoice_number;
        $numberA2 = RetailOrder::query()->where('company_id', $this->companyA->id)
            ->findOrFail((int) $orderA2['id'])->invoice_number;
        $numberB1 = RetailOrder::query()->where('company_id', $this->companyB->id)
            ->findOrFail((int) $orderB1['id'])->invoice_number;

        // Séquence continue chez A, et B repart à 1 (compteur par tenant).
        $this->assertSame("FAC-{$year}-000001", $numberA1);
        $this->assertSame("FAC-{$year}-000002", $numberA2);
        $this->assertSame("FAC-{$year}-000001", $numberB1);
    }

    public function test_invoice_rejects_non_completed_order_with_422(): void
    {
        $draft = $this->makeOrder($this->principalA, false);

        $this->get("/api/v1/retail/orders/{$draft['id']}/invoice.pdf")
            ->assertStatus(422);
    }

    public function test_invoice_is_isolated_cross_tenant(): void
    {
        $order = $this->makeOrder($this->principalA);

        $this->actingAsUser($this->principalB);

        $this->get("/api/v1/retail/orders/{$order['id']}/invoice.pdf")
            ->assertStatus(404);
    }

    public function test_endpoints_require_retail_feature_flag(): void
    {
        $order = $this->makeOrder($this->principalA);

        // Flag retiré : le middleware module.retail ferme la porte (403).
        $this->companyA->setFeature('retail', false);
        $this->companyA->save();

        $this->actingAsUser($this->principalA);

        $this->getJson("/api/v1/retail/pos/orders/{$order['id']}/receipt")
            ->assertStatus(403);
        $this->get("/api/v1/retail/orders/{$order['id']}/invoice.pdf")
            ->assertStatus(403);
    }
}

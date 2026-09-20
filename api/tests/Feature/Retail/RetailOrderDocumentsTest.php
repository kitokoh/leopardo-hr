<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7813) — Reçus et factures : ticket de caisse PDF des
 * ventes POS, facture PDF (POS + commandes web) avec numérotation légale
 * par tenant (`FAC-YYYY-NNNNNN`, immuable au rejeu, séquences isolées par
 * tenant), fail-closed (autre tenant → 404, non facturable → 422).
 */
class RetailOrderDocumentsTest extends TestCase
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

        $this->principalA = $this->principal($companyA);
        $this->principalB = $this->principal($companyB);
    }

    private function principal(Company $company): Employee
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
     * Vente POS complète (session + commande + paiement cash intégral).
     *
     * @return int id de la commande (completed)
     */
    private function completedPosOrder(Employee $actor): int
    {
        Sanctum::actingAs($actor);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-'.Str::upper(Str::random(4)),
        ])->assertStatus(201)->json('data');

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => $location['id'],
            'product_id' => $product['id'],
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
        ])->assertStatus(201);

        $session = $this->postJson('/api/v1/retail/pos/sessions', [
            'location_id' => $location['id'],
            'opening_cash_minor' => 0,
        ])->assertStatus(201)->json('data');

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => $session['id'],
            'lines' => [['product_id' => $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 3_000,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);

        return (int) $order['id'];
    }

    public function test_pos_receipt_renders_pdf(): void
    {
        $orderId = $this->completedPosOrder($this->principalA);

        $response = $this->get("/api/v1/retail/pos/orders/{$orderId}/receipt");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_receipt_requires_completed_pos_order_and_tenant(): void
    {
        $orderId = $this->completedPosOrder($this->principalA);

        // Autre tenant → 404 fail-closed.
        Sanctum::actingAs($this->principalB);
        $this->get("/api/v1/retail/pos/orders/{$orderId}/receipt")->assertStatus(404);

        // Commande draft → 422.
        Sanctum::actingAs($this->principalA);
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->findOrFail($orderId);
        $draftId = $this->draftOrderId($order);
        $this->getJson("/api/v1/retail/pos/orders/{$draftId}/receipt")->assertStatus(422);
    }

    public function test_invoice_assigns_immutable_legal_number_per_tenant(): void
    {
        $orderId = $this->completedPosOrder($this->principalA);

        $response = $this->get("/api/v1/retail/orders/{$orderId}/invoice");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->findOrFail($orderId);
        $firstNumber = $order->invoice_number;
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{6}$/', (string) $firstNumber);
        $this->assertNotNull($order->invoiced_at);

        // Rejeu : MÊME numéro (immuable — numérotation légale).
        $this->get("/api/v1/retail/orders/{$orderId}/invoice")->assertStatus(200);
        $this->assertSame($firstNumber, $order->refresh()->invoice_number);

        // Deuxième commande du même tenant : séquence suivante.
        $secondId = $this->completedPosOrder($this->principalA);
        $this->get("/api/v1/retail/orders/{$secondId}/invoice")->assertStatus(200);
        /** @var RetailOrder $second */
        $second = RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->findOrFail($secondId);
        $this->assertNotSame($firstNumber, $second->invoice_number);
        $this->assertSame(
            (int) substr((string) $firstNumber, -6) + 1,
            (int) substr((string) $second->invoice_number, -6),
        );

        // Séquence ISOLÉE par tenant : le tenant B démarre à 000001.
        $orderB = $this->completedPosOrder($this->principalB);
        $this->get("/api/v1/retail/orders/{$orderB}/invoice")->assertStatus(200);
        /** @var RetailOrder $orderBModel */
        $orderBModel = RetailOrder::query()
            ->where('company_id', (string) $this->companyB->id)
            ->findOrFail($orderB);
        $this->assertStringEndsWith('-000001', (string) $orderBModel->invoice_number);
    }

    public function test_invoice_rejects_non_completed_order(): void
    {
        $orderId = $this->completedPosOrder($this->principalA);
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->findOrFail($orderId);

        $draftId = $this->draftOrderId($order);

        $this->getJson("/api/v1/retail/orders/{$draftId}/invoice")->assertStatus(422);
    }

    /**
     * Crée une seconde commande draft (sans paiement) sur la même session.
     */
    private function draftOrderId(RetailOrder $completed): int
    {
        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Autre produit',
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'price_minor' => 500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $completed->pos_session_id,
            'lines' => [['product_id' => $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        return (int) $order['id'];
    }
}
